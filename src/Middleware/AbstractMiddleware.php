<?php

namespace CatLab\Charon\Laravel\Middleware;

use Illuminate\Http\Request;

/**
 * Class AbstractMiddleware
 * @package CatLab\Charon\Laravel\Middleware
 */
abstract class AbstractMiddleware
{
    /**
     * Get input value from a specific container.
     * @param Request $request
     * @param string $in
     * @param string $name
     * @return mixed
     */
    protected function getInput($request, $in, $name)
    {
        switch ($in)
        {
            case 'header':
                return $request->header($name);

            case 'query':
                return $request->query($name);

            case 'path':
                return $request->route($name);

            default:
                throw new \InvalidArgumentException(
                    get_class($this) . " doesn't know how to handle '" . $in . "' parameters"
                );
        }
    }

    /**
     * @param Request $request
     * @param string $in
     * @param string $name
     * @param mixed $value
     * @return void
     */
    protected function setInput($request, $in, $name, $value)
    {
        switch ($in)
        {
            case 'header':
                $request->headers->set($name, $value);
                break;

            case 'query':
                $request->query->set($name, $value);
                break;

            case 'path':
                $route = call_user_func($request->getRouteResolver());
                $route->parameter($name, $value);
                break;

            default:
                throw new \InvalidArgumentException(
                    get_class($this) . " doesn't know how to handle " . $in . " parameters"
                );
        }
    }
}