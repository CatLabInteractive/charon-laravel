<?php

namespace CatLab\Charon\Laravel\JsonApi\Resolvers;

use CatLab\Charon\Interfaces\ResourceTransformer;
use CatLab\Charon\Models\Properties\ResourceField;
use CatLab\Charon\Resolvers\RequestResolver;
use Illuminate\Support\Str;

/**
 * Class JsonApiPropertyResolver
 * @package CatLab\Charon\Laravel\Resolvers
 */
class JsonApiRequestResolver extends RequestResolver
{
    const PAGE_PARAMETER = 'number';
    const FILTER_PARAMETER = 'filter';

    /**
     * @param $request
     * @param ResourceField $field
     * @return string|null
     */
    public function getFilter($request, ResourceField $field)
    {
        if (!isset($request[self::FILTER_PARAMETER]) || !is_array($request[self::FILTER_PARAMETER])) {
            return null;
        }

        if (isset($request[self::FILTER_PARAMETER][$field->getDisplayName()])) {
            return $request[self::FILTER_PARAMETER][$field->getDisplayName()];
        }

        return null;
    }

    /**
     * @param mixed $request
     * @param ResourceField $field
     * @return bool
     */
    public function hasFilter($request, ResourceField $field)
    {
        if (!isset($request['filter']) || !is_array($request['filter'])) {
            return false;
        }

        return key_exists($field->getDisplayName(), $request['filter']);
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

        if (Str::startsWith($parameter, '-')) {
            $parameter = '!' . Str::substr($parameter, 1);
        }

        return $parameter;
    }

    /**
     * @param $request
     * @return string|null
     */
    public function getPage($request)
    {
        return intval($this->getPageParameter($request, self::PAGE_PARAMETER));
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
