<?php

namespace CatLab\Charon\Laravel\JsonApi\Models;

use CatLab\Charon\Models\Context;
use CatLab\Charon\Models\CurrentPath;

/**
 * Class JsonApiContext
 * @package CatLab\Charon\Laravel\JsonApi\Models
 */
class JsonApiContext extends Context
{
    /**
     * @var array
     */
    private $includeFields = [];

    public function includeFields($resourceType, array $fields)
    {
        $this->includeFields[$resourceType] = $fields;
        return $this;
    }

    /**
     * @param CurrentPath $fieldPath
     * @return bool|null
     */
    public function shouldShowField(CurrentPath $fieldPath)
    {
        // not set? Return null to cause 'default behaviour'
        if (count($this->includeFields) === 0) {
            return null;
        }

        $field = $fieldPath->getTopField();
        $resourceType = $field->getResourceDefinition()->getType();

        // not defined? Don't include!
        if (!isset($this->includeFields[$resourceType])) {
            return false;
        }

        return in_array($field->getDisplayName(), $this->includeFields[$resourceType]);
    }
}
