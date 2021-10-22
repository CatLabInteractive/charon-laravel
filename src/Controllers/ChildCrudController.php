<?php

namespace CatLab\Charon\Laravel\Controllers;

use CatLab\Charon\Enums\Action;
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
     * ChildCrudController constructor.
     */
    public function __construct()
    {
        if (!defined('static::RESOURCE_DEFINITION')) {
            throw ResourceException::makeTranslatable('All classes using CrudController must define a constant called RESOURCE_DEFINITION.');
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
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @param bool $isNew
     * @return Model
     */
    protected function beforeSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity, $isNew = false)
    {
        if ($isNew) {
            $relationship = $this->getRelationship($request);
            if ($relationship instanceof HasMany) {
                $this->getInverseRelationship($entity)->associate($this->getParent($request));
            }
        }

        return $entity;
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

    /**
     * Checks if user is authorized to watch an index of the entities.
     * @param Request $request
     */
    protected function authorizeIndex(Request $request)
    {
        $this->authorizeCrudRequest(Action::INDEX, null, $this->getParent($request));
    }

    /**
     * Checks if user is authorized to create an entity.
     * @param Request $request
     */
    protected function authorizeCreate(Request $request)
    {
        $this->authorizeCrudRequest(Action::CREATE, null, $this->getParent($request));
    }
}
