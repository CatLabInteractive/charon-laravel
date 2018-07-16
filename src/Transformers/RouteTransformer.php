<?php

namespace CatLab\Charon\Laravel\Transformers;

use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Laravel\Middleware\InputTransformer;
use CatLab\Charon\Laravel\Middleware\InputValidator;
use CatLab\Charon\Library\TransformerLibrary;
use CatLab\Charon\Transformers\ArrayTransformer;
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
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
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

                // Now check if the parameter has an array
                if ($parameter->getTransformer()) {

                    $middleware = $this->getTransformerMiddlewareParameters(
                        $parameter->getIn(),
                        $parameter->getType(),
                        $parameter->getName(),
                        TransformerLibrary::serialize($parameter->getTransformer())
                    );
                    $laravelRoute->middleware($middleware);
                }

                // Also check if we need to add a validator for the parameters.
                $requirements = $parameter->getRequirements();
                if (count($requirements) > 0) {
                    $middleware = $this->getValidatorMiddlewareParameters(
                        $parameter->getIn(),
                        $parameter->getType(),
                        $parameter->getName(),
                        $requirements->serialize()
                    );
                    $laravelRoute->middleware($middleware);
                }
            }

            if (isset($options['middleware'])) {
                $laravelRoute->middleware($options['middleware']);
            }
        }
    }

    /**
     * @param $container
     * @param $type
     * @param $name
     * @param $transformer
     * @return string
     */
    protected function getTransformerMiddlewareParameters($container, $type, $name, $transformer)
    {
        $middlewareProps = [
            InputTransformer::class,
        ];

        $middlewareParameters = [
            $container,
            $type,
            $name,
            $transformer
        ];

        $middlewareProps[] = implode(',', $middlewareParameters);

        return implode(':', $middlewareProps);
    }

    /**
     * @param $container
     * @param $type
     * @param $name
     * @param $serializedValidator
     * @return string
     */
    protected function getValidatorMiddlewareParameters($container, $type, $name, $serializedValidator)
    {
        $middlewareProps = [
            InputValidator::class,
        ];

        $middlewareParameters = [
            $container,
            $type,
            $name,
            $serializedValidator
        ];

        $middlewareProps[] = implode(',', $middlewareParameters);

        return implode(':', $middlewareProps);
    }
}