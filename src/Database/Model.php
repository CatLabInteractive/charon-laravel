<?php

namespace CatLab\Charon\Laravel\Database;

use CatLab\Base\Helpers\StringHelper;
use CatLab\Charon\Laravel\Exceptions\ChildAlreadyAttachedException;
use CatLab\Charon\Laravel\Exceptions\PropertySetterException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * Class Model
 * @package CatLab\Charon\Laravel\Models
 */
class Model extends \Illuminate\Database\Eloquent\Model
{
    /**
     * @var mixed
     */
    private $addedChildren;

    /**
     * @var mixed
     */
    private $removedChildren;

    /**
     * @var mixed
     */
    private $editedChildren;

    /**
     * Save model and all its children
     */
    public function saveRecursively()
    {
        $this->save();
        $this->saveTheChildren();
    }

    /**
     * Add children to a related entity.
     * @param string $relation
     * @param Model[] $childEntities
     * @param mixed[] $setterParameters
     */
    public function addChildrenToEntity($relation, array $childEntities, $setterParameters = [])
    {
        $this->addToChildArray('addedChildren', $relation, $childEntities, $setterParameters);

        foreach ($childEntities as $child) {

            $relationship = call_user_func([ $this, $relation ]);
            if ($relationship instanceof HasMany) {
                // Make sure the entry is not already attached to a different entity
                $foreignKeyName = $relationship->getForeignKeyName();
                $localKeyName = $relationship->getLocalKeyName();

                if (!is_null($child->$foreignKeyName) && $child->$foreignKeyName != $this->$localKeyName) {
                    throw ChildAlreadyAttachedException::make($this, $child, $relationship);
                }
            }

            // make sure it is also added to the local collection
            // (this automagically loads the relationships so this might cause db queries)
            $this->$relation->add($child);
        }
    }

    /**
     * @param string $relation
     * @param Model[] $childEntities
     * @param mixed[] $parameters
     */
    public function editChildrenInEntity($relation, $childEntities, $parameters)
    {
        $this->addToChildArray('editedChildren', $relation, $childEntities, $parameters);
    }

    /**
     * @param string $relation
     * @param Model[] $childEntities
     * @param mixed[] $parameters
     */
    public function removeChildrenFromEntity($relation, $childEntities, $parameters)
    {
        $this->addToChildArray('removedChildren', $relation, $childEntities, $parameters);

        foreach ($childEntities as $child) {
            if ($this->relationLoaded($relation)) {
                $this->setRelation($relation, $this->getRelation($relation)->filter(function($value, $key) use ($child) {
                    return $value->id !== $child->id;
                }));
            }
        }
    }

    /**
     * @param string $relation
     * @return Model[]
     */
    public function getAddedChildren($relation)
    {
        return $this->getChildrenFromChildArray('addedChildren', $relation);
    }

    /**
     * @param string $relation
     * @return Model[]
     */
    public function getRemovedChildren($relation)
    {
        return $this->getChildrenFromChildArray('removedChildren', $relation);
    }

    /**
     * @param string $relation
     * @return Model[]
     */
    public function getEditedChildren($relation)
    {
        return $this->getChildrenFromChildArray('editedChildren', $relation);
    }

    /**
     * Save all related entities.
     * @throws PropertySetterException
     * @throws \Exception
     */
    protected function saveTheChildren()
    {
        $toReload = [];

        $this->forEachChildrenFromChildArray(
            'addedChildren',
            function($relation, $children, $parameters) use (&$toReload) {

                // Look for magic method
                $magicMethod = 'saveMany' . Str::ucfirst($relation);
                if (method_exists($this, $magicMethod)) {
                    call_user_func([ $this, $magicMethod ], $children, $parameters);
                } else {
                    $relationship = call_user_func([$this, $relation]);
                    if ($relationship instanceof HasMany) {
                        $relationship->saveMany($children);
                    } else if ($relationship instanceof BelongsToMany) {
                        $relationship->saveMany($children);
                    } else if ($relationship instanceof MorphMany) {
                        $relationship->saveMany($children);
                    } else {
                        throw new PropertySetterException(
                            "Relationship " . get_class($relationship) . " is not implemented yet."
                        );
                    }
                }

                // Also save the grandchildren
                foreach ($children as $child) {
                    if ($child instanceof Model) {
                        $child->saveTheChildren();
                    }
                }

                $toReload[$relation] = true;
            }
        );

        $this->forEachChildrenFromChildArray(
            'removedChildren',
            function($relation, $children, $parameters) use (&$toReload) {

                $relationship = call_user_func([$this, $relation]);

                if ($relationship instanceof BelongsToMany) {
                    $ids = [];
                    foreach ($children as $child) {
                        $ids[][$child->primaryKey] = $child->{$child->primaryKey};
                    }
                    $relationship->detach($ids);
                } elseif ($relationship instanceof HasMany) {
                    foreach ($children as $child) {
                        $child->delete();
                    }
                } else {
                    throw new PropertySetterException(
                        "Relationship " . get_class($relationship) . " is not implemented yet."
                    );
                }

                $toReload[$relation] = true;
            }
        );


        $this->forEachChildrenFromChildArray(
            'editedChildren',
            function($relation, $children, $parameters) use (&$toReload) {
                foreach ($children as $child) {
                    if ($child instanceof Model) {
                        $child->saveRecursively();
                    } else {
                        $child->save();
                    }
                }

                $toReload[$relation] = true;
            }
        );

        $toReload = array_keys($toReload);
        foreach ($toReload as $reload) {
            unset($this->relations[$reload]);
        }
    }

    /**
     * @param $childArrayName
     * @param $relation
     * @param array $childEntities
     * @param array $setterParameters
     */
    private function addToChildArray($childArrayName, $relation, $childEntities, $parameters = [])
    {
        // For each relationship, keep a list of all children that were added.
        if (!isset($this->$childArrayName[$relation])) {
            $this->$childArrayName[$relation] = [];
        }

        foreach ($this->$childArrayName[$relation] as &$relationshipArray) {
            if ($relationshipArray['parameters'] === $parameters) {
                $relationshipArray['children'] = array_merge($relationshipArray['children'], $childEntities);
                return;
            }
        }

        $this->$childArrayName[$relation][] = [
            'parameters' => $parameters,
            'children' => $childEntities
        ];
    }

    /**
     * @param $childArrayName
     * @param $relation
     * @return array
     */
    private function getChildrenFromChildArray($childArrayName, $relation)
    {
        if (!isset($this->$childArrayName[$relation])) {
            return [];
        }

        $out = [];
        foreach ($this->$childArrayName[$relation] as $relationshipArray) {
            $out = array_merge($out, $relationshipArray['children']);
        }

        return $out;
    }

    /**
     * Call $callback for each list of children (and any parameters that might have been defined)
     * @param $childArrayName
     * @param callable $callback
     */
    private function forEachChildrenFromChildArray($childArrayName, callable $callback)
    {
        if (isset($this->$childArrayName)) {
            foreach ($this->$childArrayName as $relation => $childrenArrays) {
                foreach ($childrenArrays as $childrenArray) {
                    $parameters = $childrenArray['parameters'];
                    $children = $childrenArray['children'];

                    $callback($relation, $children, $parameters);
                }
            }
        }
    }
}
