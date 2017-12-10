<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use App\Repositories\Events\RepositoryEntityCreated;
use App\Repositories\Events\RepositoryEntityDeleted;
use App\Repositories\Events\RepositoryEntityUpdated;
use App\Repositories\Exceptions\RepositoryException;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class BaseRepository
 * @package App\Repositories\Eloquent
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var Model|Builder
     */
    protected $model;

    /**
     * @var
     */
    protected $relations;

    /**
     * @var Closure
     */
    protected $scopeQuery;

    /**
     * BaseRepository constructor.
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->makeModel();
    }

    /**
     * @return Model|mixed
     * @throws RepositoryException
     */
    public function makeModel()
    {
        $model = $this->app->make($this->model());

        if (!$model instanceof Model) {
            throw new RepositoryException("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
        }

        return $this->model = $model;
    }

    /**
     * Reset the model after query
     */
    protected function resetModel()
    {
        $this->makeModel();
    }

    /**
     * @return mixed
     */
    abstract public function model();

    /**
     * @return Builder|Model
     */
    public function getModel()
    {
        if ($this->model instanceof Builder) {
            return $this->model->getModel();
        }

        return $this->model;
    }

    /**
     * @return string
     */
    public function getModelTable()
    {
        if ($this->model instanceof Builder) {
            return $this->model->getModel()->getTable();
        } else {
            return $this->model->getTable();
        }
    }

    /**
     * The fake "booting" method of the model in calling scopes.
     */
    public function scopeBoot()
    {

    }

    /**
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all($columns = ['*'])
    {
        $this->scopeBoot();

        $this->applyScope();

        if ($this->model instanceof Builder) {
            $results = $this->model->get($columns);
        } else {
            $results = $this->model->all($columns);
        }

        $this->resetModel();
        $this->resetScope();

        return $results;
    }

    /**
     * @param int $perPage
     * @param array $columns
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($perPage = null, $columns = ['*'])
    {
        $this->scopeBoot();

        $this->applyScope();

        $perPage = $perPage ?: $this->getDefaultPerPage();

        $results = $this->model->paginate($perPage ?: $perPage, $columns);

        $this->resetModel();
        $this->resetScope();

        return $results;
    }

    /**
     * @return int
     */
    public function getDefaultPerPage()
    {
        return config('blog.posts.per_page', 10);
    }

    /**
     * @param array $attributes
     * @return Model
     */
    public function create(array $attributes)
    {
        return tap($this->model->create($attributes), function ($model) {
            event(new RepositoryEntityCreated($this, $model));
        });
    }

    /**
     * @param array $attributes
     * @param $id
     * @return \Illuminate\Database\Eloquent\Collection|Model
     */
    public function update(array $attributes, $id)
    {
        $this->scopeBoot();

        $this->applyScope();

        $model = $this->model->findOrFail($id);
        $model->fill($attributes);
        $model->save();

        event(new RepositoryEntityUpdated($this, $model));

        $this->resetModel();
        $this->resetScope();

        return $model;
    }

    /**
     * @param $id
     * @return int
     */
    public function delete($id)
    {
        $this->scopeBoot();

        $this->applyScope();

        $model = $this->find($id);
        $originalModel = clone $model;

        $deleted = $model->delete();

        event(new RepositoryEntityDeleted($this, $originalModel));

        $this->resetModel();
        $this->resetScope();

        return $deleted;
    }

    /**
     * @param $id
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection|Model
     */
    public function find($id, $columns = ['*'])
    {
        $this->scopeBoot();

        $this->applyScope();

        $result = $this->model->findOrFail($id, $columns);

        $this->resetModel();
        $this->resetScope();

        return $result;
    }

    /**
     * @param $field
     * @param $value
     * @param array $columns
     * @return mixed
     */
    public function findBy($field, $value, $columns = ['*'])
    {
        $this->scopeBoot();

        $this->applyScope();

        $result = $this->model->where($field, '=', $value)->first($columns);

        $this->resetModel();
        $this->resetScope();

        return $result;
    }

    /**
     * @param $field
     * @param $value
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function findAllBy($field, $value, $columns = ['*'])
    {
        $this->scopeBoot();

        $this->applyScope();

        $results = $this->model->where($field, '=', $value)->get($columns);

        $this->resetModel();
        $this->resetScope();

        return $results;
    }

    /**
     * @param array $where
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function findWhere(array $where, $columns = ['*'])
    {
        $this->scopeBoot();

        $this->applyScope();

        $this->applyConditions($where);

        $results = $this->model->get($columns);

        $this->resetModel();
        $this->resetScope();

        return $results;
    }

    /**
     * Applies the given where conditions to the model.
     *
     * @param array $where
     * @return void
     */
    protected function applyConditions(array $where)
    {
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                list($field, $condition, $val) = $value;
                $this->model = $this->model->where($field, $condition, $val);
            } else {
                $this->model = $this->model->where($field, '=', $value);
            }
        }
    }

    /**
     * Load relations
     *
     * @param array|string $relations
     *
     * @return $this
     */
    public function with($relations)
    {
        $this->model = $this->model->with($relations);

        $this->relations = is_string($relations) ? func_get_args() : $relations;

        return $this;
    }

    /**
     * @param array $attributes
     * @return Model
     */
    public function firstOrCreate(array $attributes = [])
    {
        $this->scopeBoot();

        $this->applyScope();

        $result = $this->model->firstOrCreate($attributes);

        $this->resetModel();
        $this->resetScope();

        return $result;
    }

    /**
     * @param bool $only
     * @return $this
     */
    public function trashed($only = false)
    {
        if ($only) {
            $this->model = $this->model->onlyTrashed();
        } else {
            $this->model = $this->model->withTrashed();
        }

        return $this;
    }

    /**
     * @return BaseRepository
     */
    public function onlyTrashed()
    {
        return $this->trashed(true);
    }

    /**
     * @return BaseRepository
     */
    public function withTrashed()
    {
        return $this->trashed();
    }

    /**
     * @param $id
     * @return mixed
     */
    public function restore($id)
    {
        $this->scopeBoot();

        $this->applyScope();

        $result = $this->withTrashed()->find($id)->restore();

        $this->resetModel();
        $this->resetScope();

        return $result;
    }

    /**
     * @param $id
     * @return bool|null
     */
    public function forceDelete($id)
    {
        $this->scopeBoot();

        $this->applyScope();

        $result = $this->withTrashed()->find($id)->forceDelete();

        $this->resetModel();
        $this->resetScope();

        return $result;
    }

    /**
     * @param mixed $relations
     * @return $this
     */
    public function withCount($relations)
    {
        $this->model = $this->model->withCount($relations);

        $this->relations = is_string($relations) ? func_get_args() : $relations;

        return $this;
    }

    /**
     * @param $relation
     * @return $this
     */
    public function has($relation)
    {
        $this->model = $this->model->has($relation);

        return $this;
    }

    /**
     * @param $relation
     * @param Closure|null $callback
     * @return $this
     */
    public function whereHas($relation, Closure $callback = null)
    {
        $this->model = $this->model->whereHas($relation, $callback);

        return $this;
    }

    /**
     * @param $column
     * @param string $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->model = $this->model->orderBy($column, $direction);

        return $this;
    }

    /**
     * @param Closure $callback
     * @return $this
     */
    public function scopeQuery(Closure $callback)
    {
        $this->scopeQuery = $callback;

        return $this;
    }

    /**
     * @return $this
     */
    protected function applyScope()
    {
        if (!is_null($this->scopeQuery) && is_callable($this->scopeQuery)) {
            $callback = $this->scopeQuery;
            $this->model = $callback($this->model);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function resetScope()
    {
        $this->scopeQuery = null;

        return $this;
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function first($columns = ['*'])
    {
        $this->scopeBoot();

        $this->applyScope();

        $result = $this->model->first($columns);

        $this->resetModel();
        $this->resetScope();

        return $result;
    }
}
