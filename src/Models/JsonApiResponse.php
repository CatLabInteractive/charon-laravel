<?php

namespace CatLab\Charon\Laravel\Models;

use CatLab\Charon\Interfaces\ResourceCollection;
use CatLab\Charon\Models\RESTResource;
use CatLab\Charon\Models\Values\Base\RelationshipValue;
use CatLab\Charon\Models\Values\ChildValue;
use CatLab\Charon\Models\Values\PropertyValue;

/**
 * Class JsonApiResponse
 *
 * A resource response that is formatted as a json api response.
 *
 * @package CatLab\Charon\Laravel\Models
 */
class JsonApiResponse extends ResourceResponse
{
    private $output;

    private $alreadyIncluded;

    /**
     * @return mixed
     */
    public function toArray()
    {
        return $this->toJsonApi();
    }

    /**
     * @return mixed
     */
    protected function toJsonApi()
    {
        $this->output = [];
        $this->output['data'] = [];
        $this->output['included'] = [];

        $this->alreadyIncluded = [];

        $resource = $this->getResource();
        $this->addResources($resource);

        return $this->output;
    }

    protected function addResources($resource)
    {
        if ($resource instanceof ResourceCollection) {

            foreach ($resource as $r) {
                /** @var RESTResource $r */
                $this->addResource($r);
            }
            $this->output['meta'] = $resource->getMeta();

        }
    }

    /**
     * @param RESTResource $resource
     */
    protected function addResource(RESTResource $resource)
    {
        $this->output['data'][] = $this->encodeResource($resource);
    }

    /**
     * @param RESTResource $resource
     * @return array
     */
    protected function encodeResource(RESTResource $resource)
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
        $this->output['included'][]  = $this->encodeResource($resource);
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
