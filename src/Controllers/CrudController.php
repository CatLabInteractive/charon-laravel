<?php

namespace CatLab\Charon\Laravel\Controllers;

use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Exceptions\ResourceException;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Laravel\Database\Model;
use CatLab\Charon\Exceptions\EntityNotFoundException;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use CatLab\Charon\Models\RESTResource;
use CatLab\Charon\Laravel\Contracts\Response as ResponseContract;
use CatLab\Requirements\Exceptions\RequirementValidationException;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use CatLab\Requirements\Exceptions\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait CRUDController
 *
 * This trait contains some basic functionality to easily set up a laravel crud controller.
 * Any class using this trait must also use the ResourceController trait.
 *
 * @package CatLab\Charon\Laravel\Controllers
 */
trait CrudController
{
    /*
     * Required methods
     */

    abstract function getResourceResponse($data, Context $context  = null): ResponseContract;

    abstract function getContext($action = Action::VIEW, $parameters = []) : Context;
    abstract function getResourceDefinition(): ResourceDefinition;

    abstract function getResources($queryBuilder, Context $context, $resourceDefinition = null, $records = null);

    abstract function bodyToResource(Context $context, $resourceDefinition = null) : RESTResource;
    abstract function bodyToResources(Context $context, $resourceDefinition = null) : ResourceCollection;

    abstract function getValidationErrorResponse(ResourceValidationException $e);
    abstract function notFound($id, $resource);
    abstract function toEntity(RESTResource $resource, Context $context, $existingEntity = null, $resourceDefinition = null, $entityFactory = null);

    abstract function toResources($entities, Context $context, $resourceDefinition = null) : ResourceCollection;
    abstract function toResource($entity, Context $context, $resourceDefinition = null) : RESTResource;

    use AuthorizesRequests {
        authorize as laravelAuthorize;
    }

    /**
     * @var Request
     */
    protected $request;

    /**
     * OrganisationController constructor.
     * @throws ResourceException
     */
    public function __construct()
    {
        if (!defined('static::RESOURCE_DEFINITION')) {
            throw new ResourceException("All classes using CrudController must define a constant called RESOURCE_DEFINITION. " . get_class($this) . ' does not have this constant.');
        }

        parent::__construct(static::RESOURCE_DEFINITION);
    }

    /**
     * @param Request $request
     * @return Response
     * @throws AuthorizationException
     */
    public function index(Request $request)
    {
        $this->request = $request;

        $this->authorizeIndex($request);
        $context = $this->getContext(Action::INDEX);

        $resources = $this->getResources($this->getIndexQuery($request), $context);
        return $this->getResourceResponse($resources, $context);
    }

    /**
     * View an entity
     * @param Request $request
     * @return Response
     * @throws AuthorizationException
     */
    public function view(Request $request)
    {
        $this->request = $request;

        $entity = $this->findEntity($request);
        $this->authorizeView($request, $entity);

        return $this->createViewEntityResponse($entity);
    }

    /**
     * Create a new entity
     * @param Request $request
     * @return Response
     * @throws RequirementValidationException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function store(Request $request)
    {
        $this->request = $request;

        $this->authorizeCreate($request);

        $writeContext = $this->getContext(Action::CREATE);
        $inputResources = $this->bodyToResources($writeContext);

        // first validate all resources
        foreach ($inputResources as $inputResource) {
            try {
                $inputResource->validate($writeContext);
            } catch (ResourceValidationException $e) {
                return $this->getValidationErrorResponse($e);
            }
        }

        $createdResources = new ResourceCollection();

        $readContext = $this->getContext(Action::VIEW);

        // now save all resources
        foreach ($inputResources as $inputResource) {
            $entity = $this->toEntity($inputResource, $writeContext);

            // Save the entity
            $entity = $this->saveEntity($request, $entity);

            $createdResources->add($this->toResource($entity, $readContext));
        }

        // Turn back into a resource
        if (
            count($inputResources) > 1 ||
            $inputResources->getMeta('bulk')
                // bulk is a meta flag that can be set by the input parser, to note that, even if only
                // one resource was submitted, the response should still be an array.
        ) {
            $response = $this->getResourceResponse($createdResources, $readContext);
        } else {
            // only take the first (and only) resource
            $response = $this->getResourceResponse($createdResources->first(), $readContext);
        }

        $response->setStatusCode(201);
        return $response;
    }

    /**
     * @param Request $request
     * @return ResourceResponse
     * @throws EntityNotFoundException
     * @throws RequirementValidationException
     * @throws AuthorizationException*@throws \CatLab\Requirements\Exceptions\ValidationException
     * @throws ValidationException
     */
    public function edit(Request $request)
    {
        $this->request = $request;

        $entity = $this->findEntity($request);
        if (!$entity) {
            throw new EntityNotFoundException('Could not find entity with id ' . $entity->id);
        }

        $this->authorizeEdit($request, $entity);

        $writeContext = $this->getContext(Action::EDIT);
        $inputResource = $this->bodyToResource($writeContext);

        try {
            $inputResource->validate($writeContext, $entity);
        } catch (ResourceValidationException $e) {
            return $this->getValidationErrorResponse($e);
        }

        $entity = $this->toEntity($inputResource, $writeContext, $entity);

        // Save the entity
        $entity = $this->saveEntity($request, $entity);

        // Turn back into a resource
        return $this->createViewEntityResponse($entity);
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws AuthorizationException
     */
    public function destroy(Request $request)
    {
        $this->request = $request;

        $entity = $this->findEntity($request);
        $this->authorizeDestroy($request, $entity);

        $entity->delete();

        return $this->toResponse([
            'success' => true,
            'message' => 'Successfully deleted entity.'
        ]);
    }

    /**
     *
     * @param $entity
     * @return ResourceResponse
     */
    protected function createViewEntityResponse($entity)
    {
        $readContext = $this->getContext(Action::VIEW);
        $resource = $this->toResource($entity, $readContext);

        return $this->getResourceResponse($resource, $readContext);
    }

    /**
     * @return string
     */
    protected function getEntityClassName()
    {
        return $this->getResourceDefinition()->getEntityClassName();
    }

    /**
     * @param Request $request
     * @return mixed
     */
    protected function getIndexQuery(Request $request)
    {
        return $this->callEntityMethod($request, 'query');
    }

    /**
     * Call a static method on the entity.
     * @param Request $request
     * @param $method
     * @return mixed
     */
    protected function callEntityMethod(Request $request, $method)
    {
        // We don't want to include the first argument.
        $args = func_get_args();
        array_shift($args);
        array_shift($args);

        return call_user_func_array([ $this->getEntityClassName(), $method ], $args);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    protected function findEntity(Request $request)
    {
        $id = $request->route()->parameter($this->getIdParameter());

        $entity = $this->callEntityMethod($request, 'find', $id);

        if (!$entity) {
            $this->notFound($id, $this->getEntityClassName());
        }

        return $entity;
    }

    /**
     * @return string
     */
    protected function getIdParameter()
    {
        if (defined('static::RESOURCE_ID')) {
            return static::RESOURCE_ID;
        } else {
            return 'id';
        }
    }

    /**
     * @return Request
     */
    protected function getRequest()
    {
        if (isset($this->request)) {
            return $this->request;
        }

        if (method_exists(Request::class, 'instance')) {
            return \Request::instance();
        } else {
            return \Request::getInstance();
        }
    }

    /**
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function saveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity)
    {
        $isNew = !$entity->exists;

        $entity = $this->beforeSaveEntity($request, $entity, $isNew);
        if ($entity instanceof Model) {
            $entity->saveRecursively();
        } else {
            $entity->save();
        }

        $entity = $this->afterSaveEntity($request, $entity, $isNew);

        return $entity;
    }

    /**
     * Called before saveEntity
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @param bool $isNew
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function beforeSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity, $isNew = false)
    {
        return $entity;
    }

    /**
     * Called after saveEntity
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @param bool $isNew
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function afterSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity, $isNew = false)
    {
        return $entity;
    }

    /**
     * Checks if user is authorized to watch an index of the entities.
     * @param Request $request
     * @throws AuthorizationException
     */
    protected function authorizeIndex(Request $request)
    {
        $this->authorizeCrudRequest(Action::INDEX);
    }

    /**
     * Checks if user is authorized to create an entity.
     * @param Request $request
     * @throws AuthorizationException
     */
    protected function authorizeCreate(Request $request)
    {
        $this->authorizeCrudRequest(Action::CREATE);
    }

    /**
     * Checks if user is authorized to view an entity.
     * @param Request $request
     * @param $entity
     * @throws AuthorizationException
     */
    protected function authorizeView(Request $request, $entity)
    {
        $this->authorizeCrudRequest(Action::VIEW, $entity);
    }

    /**
     * Checks if user is authorized to edit the entity.
     * @param Request $request
     * @param $entity
     * @throws AuthorizationException
     */
    protected function authorizeEdit(Request $request, $entity)
    {
        $this->authorizeCrudRequest(Action::EDIT, $entity);
    }

    /**
     * Checks if user is authorized to destroy the entity.
     * @param Request $request
     * @param $entity
     * @throws AuthorizationException
     */
    protected function authorizeDestroy(Request $request, $entity)
    {
        $this->authorizeCrudRequest(Action::DESTROY, $entity);
    }

    /**
     * @param $action
     * @param null $entity
     * @param null $subject
     * @throws AuthorizationException
     */
    protected function authorizeCrudRequest($action, $entity = null, $subject = null)
    {
        if ($entity === null) {
            $entity = $this->getEntityClassName();
        }

        $this->authorize($action, [ $entity, $subject ]);
    }
}
