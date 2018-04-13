<?php

namespace CatLab\Charon\Laravel\Middleware;

use CatLab\Charon\Library\TransformerLibrary;
use Closure;

/**
 * Class InputTransformer
 *
 * Run all applicable parameters through a transformer.
 *
 * @package CatLab\Charon\Laravel\Middleware
 */
class InputTransformer
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param string $type          Container of parameter that should be transformed (query, header, ...)
     * @param string $name          Name of parameter that should be transformed
     * @param string $transformer   Transformer that should be used to transform the parameter.
     * @return mixed
     */
    public function handle($request, Closure $next, $type, $name, $transformer)
    {
        $this->transformParameter($request, $type, $name, $transformer);
        return $next($request);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param $type
     * @param $name
     * @param $transformerName
     */
    protected function transformParameter($request, $type, $name, $transformerName)
    {
        switch ($type)
        {
            case 'header':
                $bag = $request->headers;
                break;

            case 'query':
                $bag = $request->query;
                break;

            default:
                throw new \InvalidArgumentException("InputTransformer doesn't know how to handle " . $type . " parameters");
        }

        $value = $bag->get($name);
        if ($value === null) {
            return;
        }

        $transformer = TransformerLibrary::make($transformerName);
        if (!$transformer) {
            throw new \InvalidArgumentException("Transformer " . $transformerName . " could not be created");
        }

        // Actually transform the input.
        $bag->set($name, $transformer->toParameterValue($value));
    }
}