<?php

namespace CatLab\Charon\Laravel\JsonApi\Models\Routing;

/**
 * Class RouteCollection
 * @package CatLab\Charon\Laravel\JsonApi\Models\Routing
 */
class RouteCollection extends \CatLab\Charon\Collections\RouteCollection
{
    /**
     * @param $method
     * @param $path
     * @param $action
     * @param $options
     * @return Route
     */
    protected function createRoute($method, $path, $action, $options)
    {
        return new Route($this, $method, $path, $action, $options);
    }
}
