<?php

namespace CatLab\Charon\Laravel\Database;

use CatLab\Base\Helpers\StringHelper;
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
     * @var Model[][]
     */
    private $addedChildren;

    /**
     * @var Model[][]
     */
    private $removedChildren;

    /**
     * @var Model[][]
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
        // For each relationship, keep a list of all children that were added.
        if (!isset($this->addedChildren[$relation])) {
            $this->addedChildren[$relation] = [];
        }

        foreach ($childEntities as $child) {
            $this->addedChildren[$relation][] = $child;

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
        // For each relationship, keep a list of all children that were added.
        if (!isset($this->editedChildren[$relation])) {
            $this->editedChildren[$relation] = [];
        }

        foreach ($childEntities as $childEntity) {
            $this->editedChildren[$relation][] = $childEntity;
        }
    }

    /**
     * @param string $relation
     * @param Model[] $childEntities
     * @param mixed[] $parameters
     */
    public function removeChildrenFromEntity($relation, $childEntities, $parameters)
    {
        // For each relationship, keep a list of all children that were added.
        if (!isset($this->removedChildren[$relation])) {
            $this->removedChildren[$relation] = [];
        }

        foreach ($childEntities as $child) {
            $this->removedChildren[$relation][] = $child;

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
        if (isset($this->addedChildren[$relation])) {
            return $this->addedChildren[$relation];
        }
        return [];
    }

    /**
     * @param string $relation
     * @return Model[]
     */
    public function getRemovedChildren($relation)
    {
        if (isset($this->removedChildren[$relation])) {
            return $this->removedChildren[$relation];
        }
        return [];
    }

    /**
     * @param string $relation
     * @return Model[]
     */
    public function getEditedChildren($relation)
    {
        if (isset($this->editedChildren[$relation])) {
            return $this->editedChildren[$relation];
        }
        return [];
    }

    /**
     * Save all related entities.
     * @throws PropertySetterException
     * @throws \Exception
     */
    protected function saveTheChildren()
    {
        $toReload = [];

        if (isset($this->addedChildren)) {
            foreach ($this->addedChildren as $relation => $children) {

                // Look for magic method
                $magicMethod = 'saveMany' . Str::ucfirst($relation);
                if (method_exists($this, $magicMethod)) {
                    call_user_func([ $this, $magicMethod], $children);
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
        }

        if (isset($this->removedChildren)) {
            foreach ($this->removedChildren as $relation => $children) {

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
        }

        if (isset($this->editedChildren)) {
            foreach ($this->editedChildren as $relation => $children) {
                $relationship = call_user_func([ $this, $relation ]);

                foreach ($children as $child) {
                    if ($child instanceof Model) {
                        $child->saveRecursively();
                    } else {
                        $child->save();
                    }
                }

                $toReload[$relation] = true;
            }
        }

        $toReload = array_keys($toReload);
        foreach ($toReload as $reload) {
            unset($this->relations[$reload]);
        }
    }
}
