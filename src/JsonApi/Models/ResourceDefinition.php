<?php

namespace CatLab\Charon\Laravel\JsonApi\Models;

use CatLab\Charon\Enums\Action;
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
            if ($field instanceof RelationshipField && Action::isReadContext($action)) {

                $out['properties']['relationships']['properties'][$field->getDisplayName()] = [

                ];

            } else {
                /** @var ResourceField $field */
                if ($field->hasAction($action)) {
                    $out['properties']['attributes']['properties'][$field->getDisplayName()] = $field->toSwagger($builder, $action);
                }
            }
        }

        if (count($out['properties']['attributes']['properties']) === 0) {
            $out['properties']['attributes']['properties'] = (object) [];
        }

        return $out;
    }
}
