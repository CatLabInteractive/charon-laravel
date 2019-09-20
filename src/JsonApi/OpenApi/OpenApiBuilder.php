<?php

namespace CatLab\Charon\Laravel\JsonApi\OpenApi;

use CatLab\Charon\Interfaces\ResourceDefinition as ResourceDefinitionInterface;
use CatLab\Charon\Laravel\Exceptions\InvalidResourceDefinitionException;
use CatLab\Charon\Laravel\JsonApi\Models\ResourceDefinition;
use CatLab\Charon\Swagger\SwaggerBuilder;

/**
 * Class OpenAPIBuilder
 * @package CatLab\Charon\Laravel\JsonApi\OpenApi
 */
class OpenAPIBuilder extends SwaggerBuilder
{
    protected function checkResourceDefinitionType(ResourceDefinitionInterface $resourceDefinition)
    {
        if (! $resourceDefinition instanceof ResourceDefinition) {
            throw new InvalidResourceDefinitionException(self::class . ' requires ' . ResourceDefinition::class . ' resource definitions');
        }
    }
}
