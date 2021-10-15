<?php

namespace CatLab\Charon\Laravel\Exceptions;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 *
 */
class ChildAlreadyAttachedException extends CharonHttpException
{
    /**
     * @param $parent
     * @param $child
     * @param Relation $relation
     * @return ChildAlreadyAttachedException
     */
    public static function make($parent, $child, Relation $relation)
    {
        if ($relation instanceof HasMany) {
            // Make sure the entry is not already attached to a different entity
            $foreignKeyName = $relation->getForeignKeyName();
            $localKeyName = $relation->getLocalKeyName();

            return new self(
                400,
                class_basename($child) . ' ' . $child->id . ' is already attached to a different ' .
                class_basename($parent) . ' (' . $child->$foreignKeyName . '), cannot link to ' . $parent->id
            );
        }

        return new self(
            400,
            class_basename($child) . ' ' . $child->id . ' is already attached to a different ' . class_basename($parent)
        );
    }
}
