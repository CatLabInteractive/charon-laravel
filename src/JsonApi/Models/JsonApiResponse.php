<?php

namespace CatLab\Charon\Laravel\JsonApi\Models;

use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceCollection;
use CatLab\Charon\Interfaces\SerializableResource;
use CatLab\Charon\Laravel\Models\ResourceResponse;
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
    /**
     * @var
     */
    private $output;

    /**
     * @var
     */
    private $alreadyIncluded;

    /**
     * @var RESTResource[]
     */
    private $included = [];

    public function __construct(SerializableResource $resource, Context $context = null, $status = 200, $headers = [])
    {
        $headers['Content-type'] = 'application/vnd.api+json';
        parent::__construct($resource, $context, $status, $headers);
    }

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

        // Finally, also add all included elements
        while (count($this->included) > 0) {
            // note that 'addIncluded' will add elements to $this->included
            // so we need to use this while instead of an iterator.
            $included = array_shift($this->included);
            $this->addIncluded($included);
        }

        return $this->output;
    }

    /**
     * @param $resource
     */
    protected function addResources($resource)
    {
        if ($resource instanceof ResourceCollection) {

            foreach ($resource as $r) {
                /** @var RESTResource $r */
                $this->touchIncluded($r);
                $this->output['data'][] = $this->encodeResource($r);
            }
            $this->output['meta'] = $resource->getMeta();

        } else {
            /** @var RESTResource $resource */
            $this->touchIncluded($resource);
            $this->output['data'] = $this->encodeResource($resource);
            //$this->output['meta'] = $resource->getMeta();
        }
    }

    /**
     * Touch resource.
     * @param $resource
     * @return bool TRUE if this resource is new, FALSE if it existed already
     */
    protected function touchIncluded(RESTResource $resource)
    {
        $type = $resource->getType();
        $id = $this->getIdentifier($resource);

        if (isset($this->alreadyIncluded[$type . '.' . $id])) {
            return false;
        }

        $this->alreadyIncluded[$type . '.' . $id] = true;
        return true;
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
            if ($property instanceof PropertyValue && $property->isVisible()) {
                $data['attributes'][$property->getField()->getDisplayName()] = $property->getValue();
            } elseif ($property instanceof RelationshipValue) {
                if ($property instanceof ChildValue) {
                    $data['relationships'][$property->getField()->getDisplayName()] = [
                        'data' => [
                            'id' => $this->getIdentifier($property->getChild()),
                            'type' => $property->getChild()->getType()
                        ]
                    ];

                    if ($property->getContext()->getAction() !== Action::IDENTIFIER) {
                        $this->included[] = $property->getChild();
                    }

                } else {
                    $data['relationships'][$property->getField()->getDisplayName()] = [
                        'data' => []
                    ];
                    foreach ($property->getChildren() as $child) {
                        $data['relationships'][$property->getField()->getDisplayName()]['data'][] = [
                            'id' => $this->getIdentifier($child),
                            'type' => $child->getType()
                        ];

                        if ($property->getContext()->getAction() !== Action::IDENTIFIER) {
                            $this->included[] = $child;
                        }
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
        if (!$this->touchIncluded($resource)) {
            return;
        }

        $this->output['included'][]  = $this->encodeResource($resource);
    }

    /**
     * @param RESTResource $resource
     * @return string
     */
    protected function getIdentifier(RESTResource $resource)
    {
        $identifiers = array_map(function(PropertyValue $v) {
            return (string) $v->getValue();
        }, $resource->getIdentifiers()->getValues());

        if (count($identifiers) === 1) {
            return array_shift($identifiers);
        }

        return implode('.', $identifiers);
    }
}
