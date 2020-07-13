<?php

namespace CatLab\Charon\Laravel\Resolvers;

use App\Models\User;
use CatLab\Base\Enum\Operator;
use CatLab\Charon\Collections\PropertyValueCollection;
use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Exceptions\InvalidPropertyException;
use CatLab\Charon\Exceptions\VariableNotFoundInContext;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Interfaces\ResourceTransformer;
use CatLab\Charon\Models\Properties\Base\Field;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\Charon\Models\Values\Base\RelationshipValue;
use CatLab\Charon\Models\Values\PropertyValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Class PropertyResolver
 *
 * Resolve (get) data from entities.
 *
 * Order of resolvement:
 * 1. check for getFieldNameIdentifier() method (if action === identifier)
 * 2. check for getFieldName() method
 * 3. check for laravel relationship (with support for action === identifier)
 * 4. check for isFieldName() method
 * 5. return stdObject->$fieldName
 *
 * @package CatLab\RESTResource\Laravel\Resolvers
 */
class PropertyResolver extends \CatLab\Charon\Resolvers\PropertyResolver
{
    const GETTER_PREFIX = 'get';
    const GETTER_BOOLEAN_PREFIX = 'is';
    const GETTER_IDENTIFIER_POSTFIX = 'Identifier';

    /**
     * @param $entity
     * @param $name
     * @param array $getterParameters
     * @param Context $context
     * @return mixed|null
     */
    protected function getValueFromEntity($entity, $name, array $getterParameters, Context $context)
    {
        /** @var Model $entity */

        // Check if we only want the identifier
        if ($context->getAction() === Action::IDENTIFIER && $this->methodExists($entity, self::GETTER_PREFIX.ucfirst($name).self::GETTER_IDENTIFIER_POSTFIX)) {
            return call_user_func_array(array($entity, self::GETTER_PREFIX.ucfirst($name).self::GETTER_IDENTIFIER_POSTFIX), $getterParameters);
        }

        // Check for get method
        if ($this->methodExists($entity, self::GETTER_PREFIX.ucfirst($name))) {
            return call_user_func_array(array($entity, self::GETTER_PREFIX.ucfirst($name)), $getterParameters);
        }

        // Check for laravel "relationship" method
        elseif ($this->methodExists($entity, $name)) {

            if (
                $entity instanceof Model &&
                $entity->relationLoaded($name)
            ) {
                return $entity->$name;
            } else {

                $relation = call_user_func_array(array($entity, $name), $getterParameters);

                if ($relation instanceof BelongsTo) {

                    // Do we just want the identifier?
                    if ($context->getAction() === Action::IDENTIFIER) {
                        // Create a new 'related' instance and only fill in the identifier.
                        $foreignId = $entity->getAttribute($relation->getForeignKey());
                        if ($foreignId) {
                            $instance =  $relation->getRelated()->newInstance();
                            $instance->{$relation->getOwnerKey()} = $entity->getAttribute($relation->getForeignKey());

                            return $instance;
                        } else {
                            return null;
                        }
                    }

                    $relation = $relation->get()->first();
                } elseif ($relation instanceof HasOne) {
                    $relation = $relation->get()->first();
                }

                return $relation;
            }
        }

        elseif ($this->methodExists($entity, self::GETTER_BOOLEAN_PREFIX.ucfirst($name))) {
            return call_user_func_array(array($entity, self::GETTER_BOOLEAN_PREFIX.ucfirst($name)), $getterParameters);
        }

        else {
            //throw new InvalidPropertyException;
            return $entity->$name;
        }
    }

    /**
     * @param ResourceTransformer $transformer
     * @param RelationshipField $field
     * @param mixed $parentEntity
     * @param PropertyValueCollection $identifiers
     * @param Context $context
     * @return mixed
     * @throws InvalidPropertyException
     * @throws VariableNotFoundInContext
     */
    public function getChildByIdentifiers(
        ResourceTransformer $transformer,
        RelationshipField $field,
        $parentEntity,
        PropertyValueCollection $identifiers,
        Context $context
    ) {
        /** @var Builder $entities */
        $entities = $this->resolveProperty($transformer, $parentEntity, $field, $context);

        foreach ($identifiers->toArray() as $identifier) {
            /** @var PropertyValue $identifier */
            $entities->where(
                $transformer->getQueryAdapter()->getQualifiedName($identifier->getField()),
                '=',
                $identifier->getValue()
            );
        }

        return $entities->first();
    }

    /**
     * @param ResourceTransformer $transformer
     * @param mixed $entity
     * @param RelationshipField $field
     * @param Context $context
     * @return ResourceCollection
     * @throws InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    public function resolveManyRelationship(
        ResourceTransformer $transformer,
        $entity,
        RelationshipField $field,
        Context $context
    ) {
        $models = $this->resolveProperty($transformer, $entity, $field, $context);

        if ($models instanceof Collection) {
            return $models;
        }

        if ($models instanceof Relation) {
            // Clone to avoid setting multiple filters
            $models = clone $models;

            if ($field->getRecords()) {
                $models->take($field->getRecords());
            }

            // Handle the order
            $orderBys = $field->getOrderBy();
            foreach ($orderBys as $orderBy) {
                $sortField = $field->getChildResource()->getFields()->getFromDisplayName($orderBy[0]);
                if ($sortField) {
                    $models->orderBy($transformer->getQualifiedName($sortField, $orderBy[1]));
                }
            }

            return $models;
        }

        return $models;
    }
}
