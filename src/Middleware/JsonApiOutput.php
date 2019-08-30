<?php

namespace CatLab\Charon\Laravel\Middleware;

use CatLab\Charon\Models\Values\ChildValue;
use Closure;
use CatLab\Charon\Interfaces\ResourceCollection;
use CatLab\Charon\Models\RESTResource;
use CatLab\Charon\Models\Values\Base\RelationshipValue;
use CatLab\Charon\Models\Values\PropertyValue;
use CatLab\Charon\Laravel\Models\ResourceResponse;

/**
 * Class ResourceToOutput
 * @package CatLab\Charon\Laravel\Middleware
 */
class JsonApiOutput
{
    private $output;

    private $alreadyIncluded;

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof ResourceResponse) {
           return $this->toJsonApi($response);
        }

        return $response;
    }

    /**
     * @param ResourceResponse $response
     * @return \Illuminate\Http\JsonResponse
     */
    protected function toJsonApi(ResourceResponse $response)
    {
        $this->output = [];
        $this->output['data'] = [];
        $this->output['included'] = [];

        $this->alreadyIncluded = [];


        $resource = $response->getResource();
        $this->addResources($resource);

        return \Response::json($this->output);
    }

    protected function addResources($resource)
    {
        if ($resource instanceof ResourceCollection) {

            foreach ($resource as $r) {
                /** @var RESTResource $r */
                $this->addResource($r);
            }

        }
    }

    protected function addResource(RESTResource $resource)
    {
        $this->output['data'][] = $this->getResource($resource);
    }

    protected function getResource(RESTResource $resource)
    {
        $data = [
            'id' => $this->getIdentifier($resource),
            'type' => $resource->getType(),
            'attributes' => [],
            'relationships' => []
        ];

        foreach ($resource->getProperties()->toArray() as $property) {
            if ($property instanceof PropertyValue) {
                $data['attributes'][$property->getField()->getDisplayName()] = $property->getValue();
            } elseif ($property instanceof RelationshipValue) {
                if ($property instanceof ChildValue) {
                    $this->addIncluded($property->getChild());
                    $data['relationships'][$property->getField()->getDisplayName()] = [
                        'id' => $this->getIdentifier($property->getChild()),
                        'type' => $property->getChild()->getType()
                    ];
                } else {
                    $data['relationships'][$property->getField()->getDisplayName()] = [];
                    foreach ($property->getChildren() as $child) {
                        $this->addIncluded($child);

                        $data['relationships'][$property->getField()->getDisplayName()] = [
                            'id' => $this->getIdentifier($child),
                            'type' => $child->getType()
                        ];
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param RESTResource $resource
     */
    protected function addIncluded(RESTResource $resource)
    {
        $type = $resource->getType();
        $id = $this->getIdentifier($resource);

        if (isset($this->alreadyIncluded[$type . '.' . $id])) {
            return;
        }

        $this->alreadyIncluded[$type . '.' . $id] = true;
        $this->output['included'][]  = $this->getResource($resource);
    }

    /**
     * @param RESTResource $resource
     * @return string
     */
    protected function getIdentifier(RESTResource $resource)
    {
        $identifiers = array_map(function(PropertyValue $v) {
            return $v->getValue();
        }, $resource->getIdentifiers()->getValues());

        if (count($identifiers) === 1) {
            return array_shift($identifiers);
        }

        return implode('.', $identifiers);
    }
}
