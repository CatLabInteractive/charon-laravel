<?php

namespace CatLab\Charon\Laravel\Transformers;

use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Laravel\Middleware\InputTransformer;
use \Route;

/**
 * Class RouteTransformer
 * @package CatLab\RESTResource\Laravel\Transformers
 */
class RouteTransformer
{
    /**
     * RouteTransformer constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param RouteCollection $routes
     * @return void
     */
    public function transform(RouteCollection $routes)
    {
        foreach ($routes->getRoutes() as $route) {
            $options = $route->getOptions();
            $action = $route->getAction();

            $laravelRoute = Route::match([ $route->getHttpMethod() ], $route->getPath(), $action);

            // The InputTransformer middleware makes sure that the parameters that require a
            // transformation (for example DateTimes) are transformed before the controller takes charge.
            foreach ($route->getParameters() as $parameter) {
                if ($parameter->getTransformer()) {
                    $middlewareProps = [
                        InputTransformer::class,
                    ];

                    $middlewareParameters = [
                        $parameter->getIn(),
                        $parameter->getType(),
                        $parameter->getName(),
                        get_class($parameter->getTransformer())
                    ];

                    $middlewareProps[] = implode(',', $middlewareParameters);
                    $laravelRoute->middleware(implode(':', $middlewareProps));
                }
            }

            if (isset($options['middleware'])) {
                $laravelRoute->middleware($options['middleware']);
            }
        }
    }
}