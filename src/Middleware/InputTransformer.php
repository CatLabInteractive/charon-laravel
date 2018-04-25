<?php

namespace CatLab\Charon\Laravel\Middleware;

use CatLab\Base\Helpers\ArrayHelper;
use CatLab\Charon\Library\TransformerLibrary;
use CatLab\Requirements\Enums\PropertyType;
use CatLab\Requirements\Traits\TypeSetter;
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
     * @param string $in What container should contain the input?
     * @param string $type Container of parameter that should be transformed (query, header, ...)
     * @param string $name Name of parameter that should be transformed
     * @param string $transformer Transformer that should be used to transform the parameter.
     * @return mixed
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     */
    public function handle($request, Closure $next, $in, $type, $name, $transformer)
    {
        $this->transformParameter($request, $in, $type, $name, $transformer);
        return $next($request);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param $in
     * @param $type
     * @param $name
     * @param $transformerName
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     */
    protected function transformParameter($request, $in, $type, $name, $transformerName)
    {
        switch ($in)
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

        if (ArrayHelper::isIterable($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $transformer->toParameterValue($v);
            }
        } else {
            $value = $transformer->toParameterValue($value);
        }
        
        // Actually transform the input.
        $bag->set($name, $value);
    }
}