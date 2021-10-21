<?php

namespace CatLab\Charon\Laravel\Resolvers;

use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\Context;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 *
 */
class ValueResolver
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
     * @throws \Exception
     */
    public function getValueFromEntity($entity, $name, array $getterParameters, Context $context)
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

                // if this is a new entry, relation will always be null (and calling the relationship will destroy it)
                if (!$entity->exists) {
                    return null;
                }

                if ($relation instanceof BelongsToMany) {
                    // return all the things.
                    $relation = $relation->get();
                } else if ($relation instanceof BelongsTo) {

                    // Do we just want the identifier?
                    if ($context->getAction() === Action::IDENTIFIER) {

                        // make it possible to use in older versions of laravel.
                        if (method_exists($relation, 'getForeignKeyName')) {
                            $foreignKeyName = $relation->getForeignKeyName();
                            $ownerKeyName = $relation->getOwnerKeyName();
                        } elseif (method_exists($relation, 'getForeignKey')) {
                            $foreignKeyName = $relation->getForeignKey();
                            $ownerKeyName = $relation->getOwnerKey();
                        } else {
                            throw new \Exception('Could not get foreign key from ' . get_class($relation));
                        }

                        // Create a new 'related' instance and only fill in the identifier.
                        $foreignId = $entity->getAttribute($foreignKeyName);
                        if ($foreignId) {
                            $instance =  $relation->getRelated()->newInstance();
                            $instance->{$ownerKeyName} = $entity->getAttribute($foreignKeyName);

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

        elseif (is_object($entity)) {
            //throw new InvalidPropertyException;
            return $entity->$name;
        }

        elseif (is_array($entity) && isset($entity[$name])) {
            return $entity[$name];
        }
    }

    /**
     * Drop in replacement for method_exists, with caching.
     * @param $model
     * @param $method
     * @return mixed
     */
    protected function methodExists($model, $method)
    {
        return method_exists($model, $method);
    }
}
