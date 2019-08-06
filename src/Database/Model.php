<?php

namespace CatLab\Charon\Laravel\Database;

use CatLab\Charon\Laravel\Exceptions\PropertySetterException;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
                $relationship = call_user_func([ $this, $relation ]);
                if ($relationship instanceof HasMany) {
                    $relationship->saveMany($children);
                } else {
                    throw new PropertySetterException(
                        "Relationship " . get_class($relationship) . " is not implemented yet."
                    );
                }

                // Also save the children
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
                $relationship = call_user_func([ $this, $relation ]);

                if ($relationship instanceof HasMany) {

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