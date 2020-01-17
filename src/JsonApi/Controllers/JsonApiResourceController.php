<?php


namespace CatLab\Charon\Laravel\JsonApi\Controllers;

use CatLab\Base\Helpers\ArrayHelper;
use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Enums\Cardinality;
use CatLab\Charon\Exceptions\EntityNotFoundException;
use CatLab\Charon\Factories\ResourceFactory;
use CatLab\Charon\Interfaces\RESTResource;
use CatLab\Charon\Laravel\Controllers\CrudController;
use CatLab\Charon\Laravel\Controllers\ResourceController;
use CatLab\Charon\Laravel\JsonApi\InputParsers\JsonApiInputParser;
use CatLab\Charon\Laravel\JsonApi\Models\JsonApiContext;
use CatLab\Charon\Laravel\JsonApi\Models\JsonApiResource;
use CatLab\Charon\Laravel\JsonApi\Models\JsonApiResourceCollection;
use CatLab\Charon\Laravel\JsonApi\Models\JsonApiResponse;
use CatLab\Charon\Laravel\JsonApi\Processors\PaginationProcessor;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use CatLab\Charon\Laravel\JsonApi\Resolvers\JsonApiRequestResolver;
use CatLab\Charon\Laravel\Resolvers\PropertyResolver;
use CatLab\Charon\Laravel\Resolvers\PropertySetter;
use CatLab\Charon\Laravel\Resolvers\QueryAdapter;
use CatLab\Charon\Laravel\ResourceTransformer;
use CatLab\Charon\Library\ResourceDefinitionLibrary;
use CatLab\Charon\Models\Context;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\Charon\Pagination\PaginationBuilder;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;

/**
 * Trait JsonApiResourceController
 * @package CatLab\Charon\Laravel\Controllers
 */
trait JsonApiResourceController
{
    use ResourceController, CrudController {
        CrudController::getRequest insteadof ResourceController;
    }

    /**
     * @param RouteCollection $routes
     * @param $resourceDefinition
     * @param $path
     * @param $resourceId
     * @param null $controller
     * @param null $options
     * @return RouteCollection
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setJsonApiRoutes(
        RouteCollection $routes,
        $resourceDefinition,
        $path,
        $resourceId,
        $controller = null,
        $options = []
    ) {
        if (!isset($controller)) {
            $controller = class_basename(static::class);
        }

        if (!isset($options['id'])) {
            $options['id'] = $resourceId;
        }

        if (!isset($options['only'])) {
            $options['only'] = [ 'index', 'view', 'store', 'patch', 'destroy' ];
        }

        $childResource = $routes->resource(
            $resourceDefinition,
            $path,
            $controller,
            $options
        );

        // we need to create a ResourceDefinition object to create the 'linkable' endpoints
        $resourceDefinitionObject = ResourceDefinitionLibrary::make($resourceDefinition);
        foreach ($resourceDefinitionObject->getFields()->getRelationships() as $field)  {
            self::addLinkRelationshipEndpoint($childResource, $field, $path, $resourceId, $controller, $options);
        }

        // add support for batch update
        $childResource->patch($path, $controller . '@batchEdit')
            ->summary(function () use ($resourceDefinition) {
                $entityName = ResourceDefinitionLibrary::make($resourceDefinition)
                    ->getEntityName(true);

                return 'Batch update multiple ' . $entityName;
            })
            ->parameters()->resource($resourceDefinition)->many()->required()
            ->returns()->statusCode(200)->many($resourceDefinition);

        return $childResource;
    }

    /**
     * @param RouteCollection $routes
     * @param RelationshipField $field
     * @param $path
     * @param $resourceId
     * @param null $controller
     * @param null $options
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    protected static function addLinkRelationshipEndpoint(
        RouteCollection $routes,
        RelationshipField $field,
        $path,
        $resourceId,
        $controller = null,
        $options = null
    ) {

        $resourceDefinition = ResourceDefinitionLibrary::make($field->getResourceDefinition());

        $only = isset($options['only']) ? $options['only'] : [ 'view', 'patch' ];

        if (in_array('view', $only)) {
            $routes->get($path . '/{' . $resourceId . '}/relationships/{"' . $field->getDisplayName() . '"}', $controller . '@viewRelationship')
                ->summary(function () use ($field, $resourceDefinition) {
                    $entityName = $resourceDefinition->getEntityName(false);

                    if ($field->getCardinality() === Cardinality::MANY) {
                        $relatedEntityName = $field->getChildResourceDefinition()->getEntityName(true);
                        return 'View all ' . $field->getDisplayName() . ' ' . $relatedEntityName . ' of a ' . $entityName;
                    } else {
                        $relatedEntityName = $field->getChildResourceDefinition()->getEntityName(false);
                        return 'View the ' . $field->getDisplayName() . ' ' . $relatedEntityName . ' of a ' . $entityName;
                    }
                })
                ->parameters()->path($resourceId)->string()->required()
                ->returns()->statusCode(200)->one(get_class($field->getResourceDefinition()));
        }

        /*
         * Can we link existing items to this entity?
         */
        if ($field->canLinkExistingEntities() && in_array('patch', $only)) {

            // Replace the relationship with a completely new list.
            $routes->patch($path . '/{' . $resourceId . '}/relationships/{"' . $field->getDisplayName() . '"}', $controller . '@updateRelationship')
                ->summary(function () use ($field, $resourceDefinition) {
                    $entityName = $resourceDefinition->getEntityName(false);

                    if ($field->getCardinality() === Cardinality::MANY) {
                        $relatedEntityName = $field->getChildResourceDefinition()->getEntityName(true);
                        return 'Replace all ' . $field->getDisplayName() . ' ' . $relatedEntityName . ' of a ' . $entityName;
                    } else {
                        $relatedEntityName = $field->getChildResourceDefinition()->getEntityName(false);
                        return 'Replace the ' . $field->getDisplayName()  . ' ' . $relatedEntityName . ' of a ' . $entityName;
                    }
                })
                ->parameters()->path($resourceId)->string()->required()
                ->parameters()->resource(get_class($field->getChildResourceDefinition()))->setAction(Action::IDENTIFIER)
                ->returns()->statusCode(200)->one(get_class($field->getResourceDefinition()));

            // POST relationship
            if ($field->getCardinality() === Cardinality::MANY) {
                $routes->post($path . '/{' . $resourceId . '}/relationships/{"' . $field->getDisplayName() . '"}', $controller . '@addToRelationship')
                    ->summary(function () use ($field, $resourceDefinition) {
                        $entityName = $resourceDefinition->getEntityName(false);

                        $relatedEntityName = $field->getChildResourceDefinition()->getEntityName(true);
                        return 'Add ' . $field->getDisplayName() . ' ' . $relatedEntityName . ' to a ' . $entityName;
                    })
                    ->parameters()->path($resourceId)->string()->required()
                    ->parameters()->resource(get_class($field->getChildResourceDefinition()))->setAction(Action::IDENTIFIER)->many()
                    ->returns()->statusCode(200)->one(get_class($field->getResourceDefinition()));

                $routes->delete($path . '/{' . $resourceId . '}/relationships/{"' . $field->getDisplayName() . '"}', $controller . '@addToRelationship')
                    ->summary(function () use ($field, $resourceDefinition) {
                        $entityName = $resourceDefinition->getEntityName(false);

                        $relatedEntityName = $field->getChildResourceDefinition()->getEntityName(true);
                        return 'Remove a ' . $field->getDisplayName() . ' ' . $relatedEntityName . ' from a ' . $entityName;
                    })
                    ->parameters()->path($resourceId)->string()->required()
                    ->parameters()->resource(get_class($field->getChildResourceDefinition()))->setAction(Action::IDENTIFIER)->many()
                    ->returns()->statusCode(200)->one(get_class($field->getResourceDefinition()));
            }
        }

        // @todo we should probably also be able to create resources.
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param $resourceId
     * @param $relationshipDisplayName
     * @return ResourceResponse
     * @throws EntityNotFoundException
     */
    public function viewRelationship(
        \Illuminate\Http\Request $request,
        $resourceId,
        $relationshipDisplayName
    ) {
        $this->request = $request;

        $entity = $this->findEntity($request);
        if (!$entity) {
            throw new EntityNotFoundException('Could not find entity with id ' . $entity->id);
        }

        $field = $this->getResourceDefinition()->getFields()->getFromDisplayName($relationshipDisplayName);
        if (!$field instanceof RelationshipField) {
            throw new \InvalidArgumentException('Only relationships can be viewed using this method.');
        }

        $resourceTransformer = $this->getResourceTransformer();
        $relatedResourceDefinition = $field->getChildResourceDefinition();

        switch ($field->getCardinality()) {
            case Cardinality::ONE:
                $context = $this->getContext(Action::VIEW);
                $related = $resourceTransformer->getPropertyResolver()->resolveManyRelationship(
                    $resourceTransformer,
                    $entity,
                    $field,
                    $context
                );

                $resource = $this->toResource($related, $context, $relatedResourceDefinition);
                return $this->getResourceResponse($resource, $context);

            case Cardinality::MANY:
                $context = $this->getContext(Action::INDEX);

                $relatedEntities = $resourceTransformer->getPropertyResolver()->resolveOneRelationship(
                    $resourceTransformer,
                    $entity,
                    $field,
                    $context
                );

                // fetch the records
                $resources = $resourceTransformer->getQueryAdapter()->getRecords(
                    $resourceTransformer,
                    $relatedResourceDefinition,
                    $context,
                    $relatedEntities
                );

                $resource = $this->toResources($resources, $context, $relatedResourceDefinition);
                return $this->getResourceResponse($resource, $context);

            default:
                throw new \InvalidArgumentException('Relationship has invalid cardinality.');
        }
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param $resourceId
     * @param $relationshipDisplayName
     * @throws EntityNotFoundException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function updateRelationship(
        \Illuminate\Http\Request $request,
        $resourceId,
        $relationshipDisplayName
    ) {
        $this->request = $request;

        $entity = $this->findEntity($request);
        if (!$entity) {
            throw new EntityNotFoundException('Could not find entity with id ' . $entity->id);
        }

        $this->authorizeEdit($request, $entity);

        /*

        $writeContext = $this->getContext(Action::EDIT);
        $inputResource = $this->bodyToResource($writeContext);

        try {
            $inputResource->validate($writeContext, $entity);
        } catch (ResourceValidationException $e) {
            return $this->getValidationErrorResponse($e);
        }

        $entity = $this->toEntity($inputResource, $writeContext, $entity);

        // Save the entity
        $this->saveEntity($request, $entity);

        // Turn back into a resource
        return $this->createViewEntityResponse($entity);
        */
    }

    public function addToRelationship($resourceId, $relationshipDisplayName)
    {

    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return ResourceResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function batchEdit(\Illuminate\Http\Request $request)
    {
        $this->request = $request;

        $this->authorizeCreate($request);

        $writeContext = $this->getContext(Action::EDIT);
        $inputResources = $this->bodyToResources($writeContext);

        $existingEntities = [];

        // first validate all resources
        foreach ($inputResources as $resourceId => $inputResource) {

            /** @var RESTResource $inputResource */
            $entityId = $inputResource
                ->getProperties()
                ->getIdentifiers()
                ->first()
                ->getValue();

            $entity = $this->callEntityMethod($request, 'find', $entityId);
            if (!$entity) {
                $this->notFound($entityId, $this->getEntityClassName());
            }

            $existingEntities[$resourceId] = $entity;

            try {
                $inputResource->validate($writeContext, $entity);
            } catch (ResourceValidationException $e) {
                return $this->getValidationErrorResponse($e);
            }
        }

        $createdResources = new ResourceCollection();

        $readContext = $this->getContext(Action::VIEW);

        // now save all resources
        foreach ($inputResources as $resourceId => $inputResource) {

            /** @var RESTResource $inputResource */
            $entity = $this->toEntity(
                $inputResource,
                $writeContext,
                $existingEntities[$resourceId]
            );

            // Save the entity
            $entity = $this->saveEntity($request, $entity);

            $createdResources->add($this->toResource($entity, $readContext));
        }

        // Turn back into a resource
        if (
            count($inputResources) > 0 ||
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
     * @param $data
     * @param \CatLab\Charon\Interfaces\Context|null $context
     * @return ResourceResponse
     */
    protected function getResourceResponse($data, \CatLab\Charon\Interfaces\Context $context  = null)
    {
        return new JsonApiResponse($data, $context);
    }

    /**
     * @param $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error($message)
    {
        return Response::json($this->getErrorMessage($message));
    }

    /**
     * @param string $message
     * @return array
     */
    protected function getErrorMessage($message)
    {
        return ['error' => ['message' => $message]];
    }

    /**
     * Output a resource or a collection of resources
     *
     * @param $models
     * @param array $parameters
     * @return \Illuminate\Http\JsonResponse
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    protected function output($models, array $parameters = [])
    {
        if (ArrayHelper::isIterable($models)) {
            $context = $this->getContext(Action::INDEX, $parameters);
        } else {
            $context = $this->getContext(Action::VIEW, $parameters);
        }

        $output = $this->modelsToResources($models, $context);
        return Response::json($output);
    }

    /**
     * @param Model|Model[] $models
     * @param Context $context
     * @param null $resourceDefinition
     * @return array|\mixed[]
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    protected function modelsToResources($models, Context $context, $resourceDefinition = null)
    {
        if (ArrayHelper::isIterable($models)) {
            return $this->toResources($models, $context, $resourceDefinition)->toArray();
        } elseif ($models instanceof Model) {
            return $this->toResource($models, $context, $resourceDefinition)->toArray();
        } else {
            return $models;
        }
    }

    /**
     * @param string $action
     * @param array $parameters
     * @return Context
     */
    protected function createContext($action = Action::VIEW, $parameters = [])
    {
        return new JsonApiContext($action, $parameters);
    }

    /**
     * @param string $action
     * @param array $parameters
     * @return Context|string
     */
    protected function getContext($action = Action::VIEW, $parameters = []): \CatLab\Charon\Interfaces\Context
    {
        $context = $this->createContext($action, $parameters);

        if ($toShow = Request::query('fields')) {
            $resourceDefinition = $this->getResourceDefinition();
            if (!is_array($toShow)) {
                $toShow = [ $resourceDefinition->getType() => $toShow ];
            }

            foreach ($toShow as $k => $v) {
                $context->includeFields($k, array_map('trim', explode(',', $v)));
            }
        }

        if ($toExpand = Request::query('include')) {
            $context->expandFields(array_map('trim', explode(',', $toExpand)));
        }

        $context->addProcessor(new PaginationProcessor(PaginationBuilder::class));

        $context->addInputParser(JsonApiInputParser::class);

        $context->setUrl(Request::url());

        return $context;
    }

    /**
     * @param ResourceValidationException $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getValidationErrorResponse(ResourceValidationException $e)
    {
        $errors = [];
        foreach ($e->getMessages()->toMap() as $v) {
            foreach ($v as $vv) {

                $errors[] = [
                    'title' => 'Could not decode resource.',
                    'detail' => $vv
                ];
            }
        }

        return Response::json([ 'errors' => $errors ])
            ->header('Content-type', 'application/vnd.api+json')
            ->setStatusCode(422);
    }

    /**
     * Create (and return) a resource transformer.
     * @return ResourceTransformer
     */
    protected function createResourceTransformer()
    {
        return new ResourceTransformer(
            new PropertyResolver(),
            new PropertySetter(),
            new JsonApiRequestResolver(),
            new QueryAdapter(),
            new ResourceFactory(
                JsonApiResource::class,
                JsonApiResourceCollection::class
            )
        );
    }
}
