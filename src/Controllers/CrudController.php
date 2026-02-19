<?php

namespace CatLab\Charon\Laravel\Controllers;

use CatLab\Charon\Collections\FilterCollection;
use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Exceptions\ResourceException;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Interfaces\ResourceDefinitionFactory;
use CatLab\Charon\Laravel\Database\Model;
use CatLab\Charon\Exceptions\EntityNotFoundException;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use CatLab\Charon\Laravel\ResourceTransformer;
use CatLab\Charon\Models\CurrentPath;
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
    abstract function getResourceDefinitionFactory(): ResourceDefinitionFactory;
    abstract function getResourceTransformer(): ResourceTransformer;

    abstract function getResources($queryBuilder, Context $context, $resourceDefinition = null);

    abstract function getValidationErrorResponse(ResourceValidationException $e);
    abstract function notFound($id, $resource);
    abstract function toEntity(RESTResource $resource, Context $context, $existingEntity = null, $resourceDefinition = null, $entityFactory = null);

    abstract function toResources($entities, Context $context, $resourceDefinition = null, $filterResults = null) : ResourceCollection;
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
            throw ResourceException::makeTranslatable('All classes using CrudController must define a constant called RESOURCE_DEFINITION. %s does not have this constant.', [ get_class($this) ]);
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

        $resourceDefinition = $resourceDefinition ?? $this->resourceDefinition;
        $context = $this->getContext(Action::INDEX);

        // First load the filters so that we can use these in the policy
        $filters = $this->resourceTransformer->getFilters(
            $this->getRequest()->query(),
            $resourceDefinition,
            $context
        );

        // Authorize the request (and pass the filters so that the index policy can be processed correctly)
        $this->authorizeIndex($request, $filters);

        // Get the query builder that will actually be used to load the entities
        $queryBuilder = $this->getIndexQuery($request);

        // Do the actual filtering based on the earlier calculated filters
        $filteredModels = $this->getFilteredModels($queryBuilder, $context, $filters, $resourceDefinition);

        // Turn the models into resources
        $resources = $this->toResources(
            $filteredModels->getModels(),
            $context,
            $resourceDefinition,
            $filteredModels->getFilterResults()
        );

        // And return those resources.
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
        $inputResources = $this->resourceTransformer->fromInput(
            $this->getResourceDefinitionFactory(),
            $writeContext,
            $request
        );

        // first validate all resources
        foreach ($inputResources as $inputResource) {
            try {
                $inputResource->validate($writeContext);

                // also see if we can create this entity.
                $this->authorizeCreateFromResource($request, $inputResource);

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
            try {
                $entity = $this->saveEntity($request, $entity);
            } catch (ResourceValidationException $e) {
                return $this->getValidationErrorResponse($e);
            }

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
     * @throws AuthorizationException*
     * @throws \CatLab\Requirements\Exceptions\ValidationException
     * @throws ValidationException
     */
    public function edit(Request $request)
    {
        $this->request = $request;

        $entity = $this->findEntity($request);
        if (!$entity) {
            throw EntityNotFoundException::makeTranslatable('Could not find entity with id %s.', [ $entity->id ]);
        }

        $this->authorizeEdit($request, $entity);

        // Fetch the entities resourcedefinition
        $resourceDefinition = $this->getResourceTransformer()
            ->getResourceDefinition($this->getResourceDefinitionFactory(), $entity);

        $writeContext = $this->getContext(Action::EDIT);

        $inputResource = $this->getResourceTransformer()
            ->fromInput($resourceDefinition, $writeContext, $request)
            ->first();

        try {
            $inputResource->validate($writeContext, $entity);
        } catch (ResourceValidationException $e) {
            return $this->getValidationErrorResponse($e);
        }

        $entity = $this->toEntity($inputResource, $writeContext, $entity);

        // Save the entity
        //$entity = $this->saveEntity($request, $entity);
        // Save the entity
        try {
            $entity = $this->saveEntity($request, $entity);
        } catch (ResourceValidationException $e) {
            return $this->getValidationErrorResponse($e);
        }

        // Turn back into a resource
        return $this->createViewEntityResponse($entity);
    }

    /**
     * Patch is similar to edit, but only the provided fields are validated & stored.
     * @param Request $request
     * @return ResourceResponse
     * @throws EntityNotFoundException
     * @throws RequirementValidationException
     * @throws AuthorizationException*
     * @throws \CatLab\Requirements\Exceptions\ValidationException
     * @throws ValidationException
     */
    public function patch(Request $request)
    {
        $this->request = $request;

        $entity = $this->findEntity($request);
        if (!$entity) {
            throw EntityNotFoundException::makeTranslatable('Could not find entity with id %s.', [ $entity->id ]);
        }

        $this->authorizeEdit($request, $entity);

        $resourceDefinition = $this->getResourceTransformer()
            ->getResourceDefinition($this->getResourceDefinitionFactory(), $entity);

        $writeContext = $this->getContext(Action::EDIT);
        $inputResource = $this->getResourceTransformer()->fromInput($resourceDefinition, $writeContext, $request)
            ->first();

        try {
            return $this->processPatchResource($request, $entity, $inputResource, $writeContext);
        } catch (ResourceValidationException $e) {
            return $this->getValidationErrorResponse($e);
        }
    }

    /**
     * @param Request $request
     * @param $entity
     * @param RESTResource $inputResource
     * @param Context $writeContext
     * @return ResourceResponse
     * @throws RequirementValidationException
     * @throws ResourceValidationException
     * @throws ValidationException
     */
    protected function processPatchResource(
        Request $request,
        $entity,
        \CatLab\Charon\Interfaces\RESTResource $inputResource,
        Context $writeContext
    ) {
        $inputResource->validate($writeContext, $entity, new CurrentPath(), false);

        $entity = $this->toEntity($inputResource, $writeContext, $entity);

        // Save the entity
        //$entity = $this->saveEntity($request, $entity);
        // Save the entity
        try {
            $entity = $this->saveEntity($request, $entity);
        } catch (ResourceValidationException $e) {
            return $this->getValidationErrorResponse($e);
        }

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
     * Bulk delete entities by IDs.
     * @param Request $request
     * @return Response
     * @throws AuthorizationException
     */
    public function bulkDestroy(Request $request)
    {
        $this->request = $request;

        $context = $this->getContext(Action::IDENTIFIER);

        try {
            $identifiers = $this->getResourceTransformer()->identifiersFromInput(
                $this->getResourceDefinitionFactory(),
                $context,
                $request
            );
        } catch (\InvalidArgumentException $e) {
            return $this->toResponse([
                'error' => [
                    'message' => $e->getMessage()
                ]
            ])->setStatusCode(400);
        }

        if ($identifiers->count() === 0) {
            return $this->toResponse([
                'error' => [
                    'message' => 'No IDs provided for bulk delete.'
                ]
            ])->setStatusCode(400);
        }

        $deletedCount = 0;
        foreach ($identifiers as $identifier) {
            $identifierValues = $identifier->getIdentifiers()->toMap();
            $id = $identifierValues[$this->getIdParameter()] ?? null;
            if ($id === null) {
                continue;
            }

            $entity = $this->callEntityMethod($request, 'find', $id);
            if ($entity) {
                $this->authorizeDestroy($request, $entity);
                $entity->delete();
                $deletedCount++;
            }
        }

        return $this->toResponse([
            'success' => true,
            'deleted' => $deletedCount
        ]);
    }

    /**
     *
     * @param $entity
     * @return ResourceResponse
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    protected function createViewEntityResponse($entity)
    {
        $readContext = $this->getContext(Action::VIEW);
        $resource = $this->toResource($entity, $readContext);

        return $this->getResourceResponse($resource, $readContext);
    }

    /**
     * @return string
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    protected function getEntityClassName()
    {
        return $this->getResourceDefinitionFactory()->getDefault()->getEntityClassName();
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
     * @throws \Throwable
     */
    protected function saveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity)
    {
        \DB::transaction(function() use ($request, &$entity) {

            $isNew = !$entity->exists;

            $entity = $this->beforeSaveEntity($request, $entity, $isNew);
            if ($entity instanceof Model) {
                $entity->saveRecursively();
            } else {
                $entity->save();
            }

            $entity = $this->afterSaveEntity($request, $entity, $isNew);

        });

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
     * @param FilterCollection $filters
     * @throws AuthorizationException
     */
    protected function authorizeIndex(Request $request, FilterCollection $filters)
    {
        //$this->authorizeCrudRequest(Action::INDEX, $filters);
        $this->authorize(Action::INDEX, [ $this->getEntityClassName(), $filters ]);
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
     * Similar to authorizeCreate, but it checks each individual resource instead
     * of a global 'create'.
     * @param Request $request
     * @param \CatLab\Charon\Interfaces\RESTResource $resource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function authorizeCreateFromResource(Request $request, RESTResource $resource)
    {
        // by default nothing happens.
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
