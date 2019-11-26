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
        return $this->getPageParameter($request, 'size');
    }

    /**
     * @param $request
     * @return mixed
     */
    public function getSorting($request)
    {
        $parameter = $this->getParameter($request, ResourceTransformer::SORT_PARAMETER);

        return str_replace('-', '!', $parameter);
    }

    /**
     * @param $request
     * @param $key
     * @return string|null
     */
    public function getParameter($request, $key)
    {
        if (isset($request[$key])) {
            return $request[$key];
        }
        return null;
    }

    /**
     * @param $request
     * @return string|null
     */
    public function getPage($request)
    {
        return $this->getPageParameter($request, self::PAGE_PARAMETER);
    }

    /**
     * @param $request
     * @return string|null
     */
    public function getBeforeCursor($request)
    {
        return $this->getPageParameter($request, self::CURSOR_BEFORE_PARAMETER);
    }

    /**
     * @param $request
     * @return string|null
     */
    public function getAfterCursor($request)
    {
        return $this->getPageParameter($request, self::CURSOR_AFTER_PARAMETER);
    }

    /**
     * @param $request
     * @param $key
     * @return |null
     */
    private function getPageParameter($request, $key)
    {
        if (!isset($request['page'])) {
            return null;
        }

        if (!isset($request['page'][$key])) {
            return null;
        }

        return $request['page'][$key];
    }
}
