<?php

namespace CatLab\Charon\Laravel\Middleware;

use CatLab\Base\Helpers\ArrayHelper;
use CatLab\Charon\Library\TransformerLibrary;
use CatLab\Charon\Models\Routing\Parameters\Base\Parameter;
use CatLab\Charon\Laravel\Exceptions\InputValidatorException;
use CatLab\Requirements\Collections\RequirementCollection;
use CatLab\Requirements\Collections\ValidatorCollection;
use CatLab\Requirements\Enums\PropertyType;
use CatLab\Requirements\Exceptions\PropertyValidationException;
use CatLab\Requirements\Exceptions\RequirementException;
use CatLab\Requirements\Traits\TypeSetter;
use Closure;

/**
 * Class InputTransformer
 *
 * Check if requirements of input is set.
 * Validators are always run after transformers.
 *
 * @package CatLab\Charon\Laravel\Middleware
 */
class InputValidator
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param string $in What container should contain the input?
     * @param string $type Container of parameter that should be transformed (query, header, ...)
     * @param string $name Name of parameter that should be transformed
     * @param string $validator Serialized validator collection
     * @return mixed
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws RequirementException
     */
    public function handle($request, Closure $next, $in, $type, $name, $validator)
    {
        $this->validateParameter($request, $in, $type, $name, $validator);
        return $next($request);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param $in
     * @param $type
     * @param $name
     * @param $requirementCollection
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws RequirementException
     */
    protected function validateParameter($request, $in, $type, $name, $requirementCollection)
    {
        switch ($in)
        {
            case 'header':
                $bag = $request->headers;
                break;

            case 'query':
                $bag = $request->query;
                break;

            case 'path':
                $bag = $request->attributes;
                break;

            default:
                throw new \InvalidArgumentException("InputValidator doesn't know how to handle " . $in . " parameters");
        }

        $validators = RequirementCollection::make($requirementCollection);
        if (!$validators instanceof RequirementCollection) {
            abort(500, 'Invalid input validator provided; validator needs to be instance of ValidatorCollection');
        }

        $value = $bag->get($name);

        try {

            if (!is_array($value)) {
                $value = [ $value ];
            }

            foreach ($value as $v) {
                $parameter = new Parameter($name, $type);
                $validators->validate($parameter, $v);
            }
        } catch (PropertyValidationException $e) {
            throw InputValidatorException::make($e);
        }
    }
}