<?php

namespace CatLab\Charon\Laravel\Controllers;

use CatLab\Charon\Exceptions\ResourceException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Class ChildCrudController
 * @package CatLab\Charon\Laravel\Controllers
 */
trait ChildCrudController
{
    use CrudController;

    /**
     * OrganisationController constructor.
     */
    public function __construct()
    {
        if (!defined('static::RESOURCE_DEFINITION')) {
            throw new ResourceException("All classes using CrudController must define a constant called RESOURCE_DEFINITION");
        }

        parent::__construct(static::RESOURCE_DEFINITION);
    }

    /**
     * @param Request $request
     * @return Relation
     */
    public abstract function getRelationship(Request $request) : Relation;

    /**
     * @param Request $request
     * @return Model
     */
    public abstract function getParent(Request $request) : Model;

    /**
     * @return string
     */
    public abstract function getRelationshipKey() : string;

    /**
     * @param Request $request
     * @return mixed
     */
    protected function getIndexQuery(Request $request)
    {
        return $this->getRelationship($request);
    }

    /**
     * Called before saveEntity
     * @param \Illuminate\Database\Eloquent\Model $entity
     */
    protected function beforeSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity)
    {
        $relationship = $this->getRelationship($request);
        if ($relationship instanceof HasMany) {
            $this->getInverseRelationship($entity)->associate($this->getParent($request));
        }
    }

    /**
     * Get the inverse relationship.
     * @param Model $entity
     * @return mixed
     */
    protected function getInverseRelationship(\Illuminate\Database\Eloquent\Model $entity)
    {
        return $entity->{$this->getRelationshipKey()}();
    }
}