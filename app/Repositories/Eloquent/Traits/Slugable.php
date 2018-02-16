<?php

namespace App\Repositories\Eloquent\Traits;

use Carbon\Carbon;

/**
 * Trait Slugable
 * @package App\Repositories\Eloquent\Traits
 */
trait Slugable
{
    /**
     * @param $slug
     * @param string $field
     * @return mixed
     * @throws \App\Repositories\Exceptions\RepositoryException
     * @see \App\Repositories\Contracts\Helpers\SlugableInterface::getBySlug()
     */
    public function getBySlug($slug, $field = 'slug')
    {
        return $this->findBy($field, $slug);
    }

    /**
     * Auto create slug if request slug is null
     *
     * @param $slug
     * @param $toBeTranslated
     * @return \JellyBool\Translug\Translation|mixed|string
     */
    public function autoSlug($slug, $toBeTranslated)
    {
        if (!empty($slug)) {
            return $slug;
        }

        $slug = str_slug_with_cn($toBeTranslated);

        if ($this->slugExists($slug)) {
            $slug = $this->addUniqueChar($slug);
        }

        return $slug;
    }

    /**
     * @param $slug
     * @param string $column
     * @return mixed
     */
    protected function slugExists($slug, $column = 'slug')
    {
        return $this->model->where($column, $slug)->exists();
    }

    /**
     * @param $slug
     * @return string
     */
    protected function addUniqueChar($slug)
    {
        return $slug . '-' . $this->uniqueChar();
    }

    /**
     * @return string
     */
    protected function uniqueChar()
    {
        return Carbon::now()->timestamp;
    }
}