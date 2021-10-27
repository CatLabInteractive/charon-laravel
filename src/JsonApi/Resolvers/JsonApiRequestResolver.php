<?php

namespace CatLab\Charon\Laravel\JsonApi\Resolvers;

use CatLab\Base\Enum\Operator;
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
    const SEARCH_PARAMETER = 'search';

    /**
     * @param $request
     * @param ResourceField $field
     * @param string $operator
     * @return string|null
     */
    public function getFilter($request, ResourceField $field, $operator = Operator::EQ)
    {
        $filterName = $this->determineFilterParameterNameFromOperator($operator);

        if (!isset($request[$filterName]) || !is_array($request[$filterName])) {
            return null;
        }

        if (isset($request[$filterName][$field->getDisplayName()])) {
            return $request[$filterName][$field->getDisplayName()];
        }

        return null;
    }

    /**
     * @param mixed $request
     * @param ResourceField $field
     * @param string $operator
     * @return bool
     */
    public function hasFilter($request, ResourceField $field, $operator = Operator::EQ)
    {
        $filterName = $this->determineFilterParameterNameFromOperator($operator);

        if (!isset($request[$filterName]) || !is_array($request[$filterName])) {
            return false;
        }

        return key_exists($field->getDisplayName(), $request[$filterName]);
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
     * @return string[]
     */
    public function getSorting($request)
    {
        $sortFields = $this->getParameter($request, ResourceTransformer::SORT_PARAMETER);
        if (!is_array($sortFields)) {
            $sortFields = [ $sortFields ];
        }

        foreach ($sortFields as $k => $v) {
            if (Str::startsWith($v, '-')) {
                $sortFields[$k] = '!' . Str::substr($v, 1);
            }
        }

        return $sortFields;
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
     * @param $operator
     * @return string
     */
    protected function determineFilterParameterNameFromOperator($operator)
    {
        switch ($operator) {
            case Operator::SEARCH:
                return self::SEARCH_PARAMETER;

            default:
                return self::FILTER_PARAMETER;
        }
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
