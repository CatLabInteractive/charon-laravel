<?php

namespace CatLab\Charon\Laravel\Resolvers;

use CatLab\Charon\Collections\PropertyValueCollection;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceTransformer;
use CatLab\Charon\Exceptions\InvalidPropertyException;
use CatLab\Charon\Laravel\Database\Model;
use CatLab\Charon\Laravel\PropertySetterException;
use CatLab\Charon\Models\Properties\IdentifierField;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\Charon\Models\Properties\ResourceField;
use CatLab\Charon\Interfaces\PropertyResolver as PropertyResolverContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Class PropertySetter
 * @package CatLab\RESTResource\Laravel\Resolvers
 */
class PropertySetter extends \CatLab\Charon\Resolvers\PropertySetter
{
    /**
     * @param ResourceTransformer $entity
     * @param mixed $name
     * @param ResourceField $value
     * @param array $setterParameters
     */
    protected function setChildInEntity($entity, $name, $value, $setterParameters = [])
    {
        // Check for link method name.
        $methodName = 'associate' . ucfirst($name);
        if (method_exists($entity, $methodName)) {
            array_unshift($setterParameters, $value);
            call_user_func_array(array ($entity, $methodName), $setterParameters);
        } else {
            $entity->$name()->associate($value);
        }
    }

    /**
     * @param $entity
     * @param $name
     * @param array $setterParameters
     * @throws InvalidPropertyException
     */
    protected function clearChildInEntity($entity, $name, $setterParameters = [])
    {
        // Check for link method name.
        $methodName = 'dissociate' . ucfirst($name);
        if (method_exists($entity, $methodName)) {
            call_user_func_array(array ($entity, $methodName), $setterParameters);
        } else {
            $entity->$name()->dissociate();
        }
    }

    /**
     * @param mixed $entity
     * @param string $name
     * @param mixed $value
     * @param array $setterParameters
     * @return mixed
     */
    protected function setValueInEntity($entity, $name, $value, $setterParameters = [])
    {
        // Check for set method
        if (method_exists($entity, 'set'.ucfirst($name))) {
            array_unshift($setterParameters, $value);
            return call_user_func_array(array($entity, 'set'.ucfirst($name)), $setterParameters);
        } else {
            $entity->$name = $value;
        }
    }


    /**
     * @param $entity
     * @param $name
     * @param array $childEntities
     * @param array $setterParameters
     * @return mixed|void
     * @throws PropertySetterException
     */
    protected function addChildrenToEntity($entity, $name, array $childEntities, $setterParameters = [])
    {
        if ($entity instanceof Model) {
            $entity->addChildrenToEntity($name, $childEntities, $setterParameters);
            return;
        }

        if (method_exists($entity, 'add'.ucfirst($name))) {
            array_unshift($setterParameters, $childEntities);
            return call_user_func_array(array($entity, 'add'.ucfirst($name)), $setterParameters);
        } else {
            foreach ($childEntities as $childEntity) {
                $relationship = call_user_func([ $entity, $name ]);

                if ($relationship instanceof BelongsToMany) {
                    $relationship->attach($childEntity);
                } else {
                    throw new PropertySetterException("Relationship of type " . get_class($relationship) . " is not " .
                        "supported yet. Use " . Model::class . " instead.");
                }
            }
        }
    }

    /**
     * @param $entity
     * @param $name
     * @param array $childEntities
     * @param $parameters
     * @throws InvalidPropertyException
     */
    protected function editChildrenInEntity($entity, $name, array $childEntities, $parameters = [])
    {
        if ($entity instanceof Model) {
            $entity->editChildrenInEntity($name, $childEntities, $parameters);
            return;
        }

        return parent::editChildrenInEntity($entity, $name, $childEntities, $parameters);
    }

    /**
     * @param ResourceTransformer $transformer
     * @param PropertyResolverContract $propertyResolver
     * @param $entity
     * @param RelationshipField $field
     * @param PropertyValueCollection[] $identifiers
     * @param Context $context
     * @return mixed
     */
    public function removeAllChildrenExcept(
        ResourceTransformer $transformer,
        PropertyResolverContract $propertyResolver,
        $entity,
        RelationshipField $field,
        array $identifiers,
        Context $context
    ) {
        list ($entity, $name, $parameters) = $this->resolvePath($transformer, $entity, $field, $context);
        $existingChildren = $this->getValueFromEntity($entity, $name, $parameters);

        if ($existingChildren instanceof Relation) {
            $children = clone $existingChildren;

            if (count($identifiers) > 0) {
                $children->where(function ($builder) use ($identifiers) {
                    foreach ($identifiers as $item) {
                        /** @var PropertyValueCollection $item */
                        $builder->where(function ($builder) use ($item) {
                            foreach ($item->toMap() as $k => $v) {
                                $builder->orWhere($k, '!=', $v);
                            }
                        });
                    }
                });
            }

            $toRemove = $children->get();
            if (count($toRemove) > 0) {
                $this->removeChildren($transformer, $entity, $field, $toRemove, $context);
            }

        } else {
            return parent::removeAllChildrenExcept(
                $transformer,
                $propertyResolver,
                $entity,
                $field,
                $identifiers,
                $context
            );
        }
    }

    /**
     * @param $entity
     * @param $name
     * @param array $childEntities
     * @param array $parameters
     * @throws PropertySetterException
     */
    protected function removeChildrenFromEntity($entity, $name, $childEntities, $parameters = [])
    {
        if ($entity instanceof Model) {
            $entity->removeChildrenFromEntity($name, $childEntities, $parameters);
            return;
        }

        // Check for add method
        if (method_exists($entity, 'remove'.ucfirst($name))) {
            array_unshift($parameters, $childEntities);
            call_user_func_array(array($entity, 'remove'.ucfirst($name)), $parameters);
        } else {

            $relationship = $entity->$name();

            if ($relationship instanceof BelongsToMany) {
                foreach ($childEntities as $childEntity) {
                    $relationship->detach($childEntity);
                }
            } else {
                throw new PropertySetterException("Relationship of type " . get_class($relationship) . " is not " .
                    "supported yet. Use " . Model::class . " instead.");
            }
        }
    }
}