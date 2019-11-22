<?php

namespace CatLab\Charon\Laravel;

use CatLab\Base\Models\Database\SelectQueryParameters;
use CatLab\Laravel\Database\SelectQueryTransformer;

/**
 * Class ResourceTransformer
 * @package CatLab\RESTResource\Laravel\Transformers
 */
class ResourceTransformer extends \CatLab\Charon\ResourceTransformer
{
    /**
     * Apply processor filters (= filters that are created by processors) and translate them to the framework specific
     * query builder.
     * @param $queryBuilder
     * @param SelectQueryParameters $parameters
     * @return void
     */
    public function applyProcessorFilters($queryBuilder, SelectQueryParameters $parameters)
    {
        // Apply the catlab query parameters to the laravel query builder.
        $selectQueryTransformer = new SelectQueryTransformer();
        $selectQueryTransformer->toLaravel($queryBuilder, $parameters);
    }
}
