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
    private $globalIncludeFields = [];

    /**
     * @var array
     */
    private $includeFields = [];

    /**
     * @param $resourceType
     * @param array $fields
     * @return $this
     */
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
        $field = $fieldPath->getTopField();

        // check if this field was included
        if (in_array($field->getDisplayName(), $this->globalIncludeFields)) {
            return true;
        }

        // not set? Return null to cause 'default behaviour'
        if (count($this->includeFields) === 0) {
            return null;
        }

        $resourceType = $field->getResourceDefinition()->getType();

        // not defined? Don't include!
        if (!isset($this->includeFields[$resourceType])) {
            return false;
        }

        return in_array($field->getDisplayName(), $this->includeFields[$resourceType]);
    }

    /**
     * @param string $field
     * @return $this
     */
    public function expandField($field)
    {
        parent::expandField($field);

        // also include globally
        $this->globalIncludeFields[] = $field;

        return $this;
    }
}
