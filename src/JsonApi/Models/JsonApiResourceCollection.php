<?php

namespace CatLab\Charon\Laravel\JsonApi\Models;

use CatLab\Charon\Collections\ResourceCollection;

/**
 * Class JsonApiResourceCollection
 * @package CatLab\Charon\Laravel\JsonApi\Models
 */
class JsonApiResourceCollection extends ResourceCollection
{
    /**
     * @param $reference
     * @return array
     */
    public function getSwaggerDescription($reference)
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'items' => [
                        '$ref' => $reference
                    ]
                ]
            ]
        ];
    }
}
