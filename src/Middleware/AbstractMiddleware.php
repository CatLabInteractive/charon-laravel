<?php

namespace CatLab\Charon\Laravel\Middleware;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Class AbstractMiddleware
 * @package CatLab\Charon\Laravel\Middleware
 */
abstract class AbstractMiddleware
{
    /**
     * @param $name
     * @return string
     */
    protected function bracketToDotNotation($name)
    {
        if (!Str::contains($name, '[')) {
            return $name;
        }

        $name = str_replace('[', '.', $name);
        $name = str_replace(']', '', $name);

        return $name;
    }

    /**
     * Get input value from a specific container.
     * @param Request $request
     * @param string $in
     * @param string $name
     * @return mixed
     */
    protected function getInput($request, $in, $name)
    {
        $name = $this->bracketToDotNotation($name);
        if (!Str::contains($name, '.')) {
            return $this->getRawInput($request, $in, $name);
        }

        $parts = explode('.', $name);
        $input = $this->getRawInput($request, $in, array_shift($parts));
        if (!is_array($input)) {
            return null;
        }

        return Arr::get($input, implode('.', $parts));
    }

    /**
     * @param $request
     * @param $in
     * @param $name
     * @return mixed
     */
    protected function getRawInput($request, $in, $name)
    {
        switch ($in)
        {
            case 'header':
                return $request->header($name);

            case 'query':
                return $request->query($name);

            case 'path':
                return $request->route($name);

            case 'body':
                return $request->getContent();

            default:
                throw new \InvalidArgumentException(
                    get_class($this) . " doesn't know how to handle '" . $in . "' parameters"
                );
        }
    }

    protected function setInput($request, $in, $name, $value)
    {
        $name = $this->bracketToDotNotation($name);
        if (!Str::contains($name, '.')) {
            return $this->setRawInput($request, $in, $name, $value);
        }

        $parts = explode('.', $name);
        $name = array_shift($parts);

        $input = $this->getRawInput($request, $in, $name);
        if (!is_array($input)) {
            $input = [];
        }
        Arr::set($input, implode('.', $parts), $value);

        $this->setRawInput($request, $in, $name, $input);
    }

    /**
     * @param Request $request
     * @param string $in
     * @param string $name
     * @param mixed $value
     * @return void
     */
    protected function setRawInput($request, $in, $name, $value)
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
                /** @var Route $route */
                $route = call_user_func($request->getRouteResolver());
                $route->setParameter($name, $value);
                break;

            default:
                throw new \InvalidArgumentException(
                    get_class($this) . " doesn't know how to handle " . $in . " parameters"
                );
        }
    }
}
