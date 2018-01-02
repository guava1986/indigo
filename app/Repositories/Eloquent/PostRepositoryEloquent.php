<?php

namespace App\Repositories\Eloquent;

use App\Http\Resources\Post as PostResource;
use App\Models\Category;
use App\Models\Content;
use App\Models\Post;
use App\Models\Tag;
use App\Repositories\Contracts\PostRepository;
use App\Repositories\Contracts\TagRepository;
use App\Repositories\Eloquent\Traits\FieldsHandler;
use App\Repositories\Eloquent\Traits\Slugable;
use App\Repositories\Exceptions\RepositoryException;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PostRepositoryEloquent
 * @package App\Repositories\Eloquent
 */
class PostRepositoryEloquent extends BaseRepository implements PostRepository
{
    use Slugable;
    use FieldsHandler;

    /**
     * @var TagRepository
     */
    protected $tagRepository;

    /**
     * @var mixed
     */
    protected $contentModel;

    /**
     * PostRepositoryEloquent constructor.
     * @param Container $app
     * @param TagRepository $tagRepository
     * @throws RepositoryException
     */
    public function __construct(Container $app, TagRepository $tagRepository)
    {
        parent::__construct($app);

        $this->tagRepository = $tagRepository;
        $this->contentModel = $this->app->make($this->contentModel());
    }

    /**
     * @return string
     */
    public function contentModel()
    {
        return Content::class;
    }

    /**
     * @return null|string
     */
    public function resource()
    {
        return PostResource::class;
    }

    /**
     *
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * @return string
     */
    public function model()
    {
        return Post::class;
    }

    /**
     * TODO return post instance
     *
     * @param array $attributes
     * @return Model
     * @throws RepositoryException
     */
    public function create(array $attributes)
    {
        $attributes = $this->preHandleData($attributes);

        // TODO use transaction
        $this->model = request()->user()->posts()->create(array_merge($attributes, [
            'content_id' => $this->contentModel->create($attributes)->id,
        ]));

        return $this->syncTags(data_get($attributes, 'tag', []));
    }

    /**
     * @param array $attributes
     * @return array
     */
    protected function preHandleData(array $attributes)
    {
        $attributes = $this->autoSlug($attributes, 'title');

        foreach ($attributes as $field => $value) {
            if (method_exists($this, $method = 'handle' . ucfirst(camel_case($field)))) {
                array_set($attributes, $field, call_user_func_array([$this, $method], [$value]));
            }
        }

        $attributes = $this->handleImg($attributes);

        // TODO excerpt should be html purifier

        return $attributes;
    }

    /**
     * @param array $tags
     * @return mixed
     * @throws RepositoryException
     */
    protected function syncTags(array $tags)
    {
        if (!$this->model->exists) {
            throw new RepositoryException('Model is not exist');
        }

        $ids = [];

        if (empty($tags)) {
            return $this->model->tags()->sync($ids);
        }

        foreach ($tags as $tagName) {
            $tag = $this->tagRepository->firstOrCreate([
                'name' => $tagName,
                'slug' => str_slug($tagName)
            ]);
            array_push($ids, $tag->id);
        }

        return $this->model->tags()->sync($ids);
    }

    /**
     * @param null $perPage
     * @param array $columns
     * @return mixed
     * @throws \App\Repositories\Exceptions\RepositoryException
     */
    public function paginate($perPage = null, $columns = ['*'])
    {
        $this->withRelationships();

        return parent::paginate($perPage ?: $this->getDefaultPerPage(), $columns);
    }

    /**
     * @return $this
     */
    protected function withRelationships()
    {
        return $this->with($this->relationships());
    }

    /**
     * @return array
     */
    protected function relationships()
    {
        return ['author', 'category', 'tags'];
    }

    // /**
    //  * Fetch posts data of home page with pagination.
    //  *
    //  * Alert: It's not optimized without cache support,
    //  * so just only use this while with cache enabled.
    //  *
    //  * @param null $perPage
    //  * @return mixed
    //  */
    // public function lists($perPage = null)
    // {
    //     $perPage = $perPage ?: $this->getDefaultPerPage();
    //
    //     // Second layer cache
    //     $pagination = $this->paginate($perPage, ['slug']);
    //
    //     $items = $pagination->getCollection()->map(function ($post) {
    //         // First layer cache
    //         return $this->getBySlug($post->slug);
    //     });
    //
    //     return $pagination->setCollection($items);
    // }

    /**
     * @return int
     */
    public function getDefaultPerPage()
    {
        return config('blog.posts.per_page');
    }

    /**
     * TODO return post instance
     *
     * @param array $attributes
     * @param $id
     * @return \Illuminate\Database\Eloquent\Collection|Model
     * @throws RepositoryException
     */
    public function update(array $attributes, $id)
    {
        $attributes = $this->preHandleData($attributes);

        // TODO use transaction
        $this->model = parent::update(array_except($attributes, 'slug'), $id);

        $this->model->content()->update($attributes);

        return $this->syncTags(data_get($attributes, 'tag', []));
    }

    /**
     * @param $slug
     * @return mixed
     * @throws \App\Repositories\Exceptions\RepositoryException
     */
    public function getBySlug($slug)
    {
        return $this->withRelationships()->firstBy('slug', $slug);
    }

    /**
     * @param \App\Models\Post $model
     * @return mixed
     * @throws \App\Repositories\Exceptions\RepositoryException
     */
    public function previous(Post $model)
    {
        return $this->scopeQuery(function ($query) use ($model) {
            return $query->previous($model->id, ['title', 'slug']);
        })->first();
    }

    /**
     * @param \App\Models\Post $model
     * @return mixed
     * @throws \App\Repositories\Exceptions\RepositoryException
     */
    public function next(Post $model)
    {
        return $this->scopeQuery(function ($query) use ($model) {
            return $query->next($model->id, ['title', 'slug']);
        })->first();
    }

    /**
     * @param int $limit
     * @return mixed
     * @throws \App\Repositories\Exceptions\RepositoryException
     */
    public function hot($limit = 5)
    {
        // TODO cache support
        return $this->scopeQuery(function ($query) use ($limit) {
            return $query->hot($limit, ['slug', 'title', 'view_count']);
        })->all();
    }

    /**
     * @param \App\Models\Category $category
     * @return mixed
     * @throws \App\Repositories\Exceptions\RepositoryException
     */
    public function paginateOfCategory(Category $category)
    {
        return $this->paginateOfPostRelated($category);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return mixed
     * @throws \App\Repositories\Exceptions\RepositoryException
     */
    protected function paginateOfPostRelated(Model $model)
    {
        if (method_exists($model, $relation = 'posts')) {
            return $model->$relation()->with($this->relationships())->paginate($this->getDefaultPerPage());
        }

        throw new RepositoryException("Current model " . get_class($model) . " doesn't have relationship of '{$relation}'.");
    }

    /**
     * @param \App\Models\Tag $tag
     * @return mixed
     * @throws \App\Repositories\Exceptions\RepositoryException
     */
    public function paginateOfTag(Tag $tag)
    {
        return $this->paginateOfPostRelated($tag);
    }
}
