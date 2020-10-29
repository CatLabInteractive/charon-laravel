<?php

namespace CatLab\Charon\Laravel\JsonApi\OpenApi;

use CatLab\Base\Helpers\ArrayHelper;
use CatLab\Charon\Enums\Cardinality;
use CatLab\Charon\Exceptions\SwaggerMultipleInputParsers;
use CatLab\Charon\Factories\ResourceFactory;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\DescriptionBuilder;
use CatLab\Charon\Interfaces\ResourceDefinition as ResourceDefinitionInterface;
use CatLab\Charon\Laravel\Exceptions\InvalidResourceDefinitionException;
use CatLab\Charon\Laravel\JsonApi\Models\JsonApiResource;
use CatLab\Charon\Laravel\JsonApi\Models\JsonApiResourceCollection;
use CatLab\Charon\Laravel\JsonApi\Models\ResourceDefinition;
use CatLab\Charon\Models\Routing\Route;
use CatLab\Charon\OpenApi\OpenApiException;
use CatLab\Charon\OpenApi\V2\OpenApiV2Builder;

/**
 * Class OpenAPIBuilder
 * @package CatLab\Charon\Laravel\JsonApi\OpenApi
 */
class OpenAPIBuilder extends OpenApiV2Builder
{
    /**
     * SwaggerBuilder constructor.
     * @param string $host
     * @param string $basePath
     */
    public function __construct(
        string $host,
        string $basePath
    ) {
        parent::__construct(
            $host,
            $basePath,
            new ResourceFactory(JsonApiResource::class, JsonApiResourceCollection::class)
        );
    }

    /**
     * @param ResourceDefinitionInterface $resourceDefinition
     * @throws InvalidResourceDefinitionException
     */
    protected function checkResourceDefinitionType(ResourceDefinitionInterface $resourceDefinition)
    {
        if (! $resourceDefinition instanceof ResourceDefinition) {
            throw new InvalidResourceDefinitionException(self::class . ' requires ' . ResourceDefinition::class . ' resource definitions');
        }
    }

    /**
     * @param string $name
     * @param string $reference
     * @param string $action
     * @return mixed
     */
    protected function addItemDefinition(string $name, string $reference, string $action) : string
    {
        $name = $name . '_' . $action . '_root';
        if (!array_key_exists($name, $this->schemas)) {

            $this->schemas[$name] = [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        '$ref' => $reference
                    ]
                ]
            ];
        }
        return '#/definitions/' . $name;
    }
}
