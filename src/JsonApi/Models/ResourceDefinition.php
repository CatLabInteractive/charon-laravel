<?php

namespace CatLab\Charon\Laravel\JsonApi\Models;

use CatLab\Charon\Enums\Action;
use CatLab\Charon\Enums\Cardinality;
use CatLab\Charon\Models\Properties\Base\Field;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\Charon\Models\Properties\ResourceField;
use CatLab\Charon\Swagger\SwaggerBuilder;

/**
 * Class ResourceDefinition
 * @package CatLab\Charon\Laravel\JsonApi\Models
 */
abstract class ResourceDefinition extends \CatLab\Charon\Models\ResourceDefinition
{
    /**
     * @param SwaggerBuilder $builder
     * @param string $action
     * @return mixed[]
     */
    public function toSwagger(SwaggerBuilder $builder, $action)
    {
        $out = [];

        $out['type'] = 'object';
        $out['properties'] = [];

        $out['properties']['id'] = [
            'type' => 'string'
        ];

        $out['properties']['type'] = [
            'type' => 'string'
        ];

        // Identifier context doesn't need anything else.
        if (Action::isIdentifierContext($action)) {
            return $out;
        }

        $out['properties']['attributes'] = [
            'type' => 'object',
            'properties' => []
        ];

        $out['properties']['relationships'] = [
            'type' => 'object',
            'properties' => []
        ];

        foreach ($this->getFields() as $field) {

            /** @var Field $field */
            $expandedFieldPath = $this->getSwaggerFieldContainer(
                $field->getDisplayName(),
                $out['properties']['attributes']['properties']
            );

            $displayName = $expandedFieldPath[0];
            $fieldContainer = &$expandedFieldPath[1]; // yep, by references. that's how we roll.

            if ($field instanceof RelationshipField && Action::isReadContext($action)) {
                $fieldContainer[$displayName] = $this->getRelationshipPropertySwaggerDescription($field);
            } else {
                /** @var ResourceField $field */
                if ($field->hasAction($action)) {
                    $fieldContainer[$displayName] = $field->toSwagger($builder, $action);
                }
            }
        }

        if (count($out['properties']['attributes']['properties']) === 0) {
            $out['properties']['attributes']['properties'] = (object) [];
        }

        return $out;
    }

    /**
     * Resolve the dot notation in
     * @param $fieldName
     * @param $container
     * @return array
     */
    private function getSwaggerFieldContainer($fieldName, &$container)
    {
        $fieldNamePath = explode('.', $fieldName);
        while (count($fieldNamePath) > 1) {
            $subPath = array_shift($fieldNamePath);
            $container[$subPath] = [
                'type' => 'object',
                'properties' => []
            ];

            $container = &$container[$subPath]['properties'];
        }

        return [ array_shift($fieldNamePath), &$container ];
    }

    /**
     * @param RelationshipField $field
     * @return array
     */
    private function getRelationshipPropertySwaggerDescription(RelationshipField $field)
    {
        $description = [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'string'
                ],
                'type' => [
                    'type' => 'string'
                ]
            ]
        ];

        if ($field->getCardinality() === Cardinality::ONE) {
            return [
                'type' => 'object',
                'properties' => [
                    'data' => $description
                ]
            ];
        } else {
            return [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => $description
                    ]
                ]
            ];
        }
    }
}
