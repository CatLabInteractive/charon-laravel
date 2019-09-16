<?php

namespace CatLab\Charon\Laravel\Resolvers;

use CatLab\Charon\Interfaces\ResourceTransformer;
use CatLab\Charon\Models\Properties\ResourceField;
use CatLab\Charon\Resolvers\RequestResolver;

/**
 * Class JsonApiPropertyResolver
 * @package CatLab\Charon\Laravel\Resolvers
 */
class JsonApiRequestResolver extends RequestResolver
{
    /**
     * @param $request
     * @param ResourceField $field
     * @return string|null
     */
    public function getFilter($request, ResourceField $field)
    {
        if (!isset($request['filter']) || !is_array($request['filter'])) {
            return null;
        }

        if (isset($request['filter'][$field->getDisplayName()])) {
            return $request['filter'][$field->getDisplayName()];
        }

        return null;
    }

    /**
     * @param $request
     * @return mixed
     */
    public function getRecords($request)
    {
        return $this->getParameter($request, ResourceTransformer::LIMIT_PARAMETER);
    }

    /**
     * @param $request
     * @return mixed
     */
    public function getSorting($request)
    {
        return $this->getParameter($request, ResourceTransformer::SORT_PARAMETER);
    }
}
