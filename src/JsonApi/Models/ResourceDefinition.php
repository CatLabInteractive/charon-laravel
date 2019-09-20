<?php

namespace CatLab\Charon\Laravel\JsonApi\Models;

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
        foreach ($this->getFields() as $field) {
            /** @var ResourceField $field */
            if ($field->hasAction($action)) {
                $out['properties'][$field->getDisplayName()] = $field->toSwagger($builder, $action);
            }
        }

        if (count($out['properties']) === 0) {
            $out['properties'] = (object) [];
        }

        return $out;
    }
}
