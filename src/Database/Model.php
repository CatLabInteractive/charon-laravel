<?php

namespace CatLab\Charon\Laravel\Database;

use CatLab\Base\Helpers\StringHelper;
use CatLab\Charon\Laravel\Exceptions\PropertySetterException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * Add children to a related entity.
     * @param $name
     * @param array $childEntities
     * @param array $setterParameters
     */
    public function addChildrenToEntity($name, array $childEntities, $setterParameters = [])
    {
        // For each relationship, keep a list of all children that were added.
        if (!isset($this->addedChildren[$name])) {
            $this->addedChildren[$name] = [];
        }

        foreach ($childEntities as $child) {
            $this->$name->add($child);
            $this->addedChildren[$name][] = $child;
        }
    }

    /**
     *
     */
    public function editChildrenInEntity($name, $childEntities, $parameters)
    {
        // For each relationship, keep a list of all children that were added.
        if (!isset($this->editedChildren[$name])) {
            $this->editedChildren[$name] = [];
        }

        foreach ($childEntities as $childEntity) {
            $this->editedChildren[$name][] = $childEntity;
        }
    }

    /**
     * @param $name
     * @param $childEntities
     * @param $parameters
     */
    public function removeChildrenFromEntity($name, $childEntities, $parameters)
    {
        // For each relationship, keep a list of all children that were added.
        if (!isset($this->removedChildren[$name])) {
            $this->removedChildren[$name] = [];
        }

        foreach ($childEntities as $child) {
            $this->removedChildren[$name][] = $child;
        }

        //$this->$name = $this->$name->except($childEntities);
    }
}
