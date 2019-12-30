<?php


namespace CatLab\Charon\Laravel\JsonApi\Controllers;

use CatLab\Base\Helpers\ArrayHelper;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Enums\Cardinality;
use CatLab\Charon\Exceptions\EntityNotFoundException;
use CatLab\Charon\Factories\ResourceFactory;
use CatLab\Charon\Laravel\Controllers\CrudController;
use CatLab\Charon\Laravel\Controllers\ResourceController;
use CatLab\Charon\Laravel\JsonApi\InputParsers\JsonApiInputParser;
use CatLab\Charon\Laravel\JsonApi\Models\JsonApiResource;
use CatLab\Charon\Laravel\JsonApi\Models\JsonApiResourceCollection;
use CatLab\Charon\Laravel\JsonApi\Models\JsonApiResponse;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use CatLab\Charon\Laravel\Processors\PaginationProcessor;
use CatLab\Charon\Laravel\JsonApi\Resolvers\JsonApiRequestResolver;
use CatLab\Charon\Laravel\Resolvers\PropertyResolver;
use CatLab\Charon\Laravel\Resolvers\PropertySetter;
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
     * @return RouteCollection
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setJsonApiRoutes(
        RouteCollection $routes,
        $resourceDefinition,
        $path,
        $resourceId,
        $controller = null
    ) {
        if (!isset($controller)) {
            $controller = class_basename(static::class);
        }

        $childResource = $routes->resource(
            $resourceDefinition,
            $path,
            $controller,
            [
                'id' => $resourceId,
                'only' => [ 'index', 'view', 'store', 'patch', 'destroy' ]
            ]
        );

        // we need to create a ResourceDefinition object to create the 'linkable' endpoints
        $resourceDefinition = ResourceDefinitionLibrary::make($resourceDefinition);
        foreach ($resourceDefinition->getFields()->getRelationships() as $field)  {
            self::addLinkRelationshipEndpoint($childResource, $field, $path, $resourceId, $controller);
        }

        return $childResource;
    }

    /**
     * @param RouteCollection $routes
     * @param RelationshipField $field
     * @param $path
     * @param $resourceId
     * @param null $controller
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    protected static function addLinkRelationshipEndpoint(
        RouteCollection $routes,
        RelationshipField $field,
        $path,
        $resourceId,
        $controller = null
    ) {

        $resourceDefinition = ResourceDefinitionLibrary::make($field->getResourceDefinition());

        $routes->get($path . '/{' . $resourceId . '}/relationships/' . $field->getDisplayName(), $controller . '@viewRelationship')
            ->summary(function () use ($field, $resourceDefinition) {
                $entityName = $resourceDefinition->getEntityName(false);

                if ($field->getCardinality() === Cardinality::MANY) {
                    $relatedEntityName = $field->getChildResourceDefinition()->getEntityName(true);
                    return 'View all ' . $field->getDisplayName() . ' ' . $relatedEntityName . ' of a ' . $entityName;
                } else {
                    $relatedEntityName = $field->getChildResourceDefinition()->getEntityName(false);
                    return 'View the ' . $field->getDisplayName()  . ' ' . $relatedEntityName . ' of a ' . $entityName;
                }
            })
            ->parameters()->path($resourceId)->string()->required()
            ->returns()->statusCode(200)->one(get_class($field->getResourceDefinition()));

        /*
         * Can we link existing items to this entity?
         */
        if ($field->canLinkExistingEntities()) {

            // Replace the relationship with a completely new list.
            $routes->patch($path . '/{' . $resourceId . '}/relationships/' . $field->getDisplayName(), $controller . '@updateRelationship')
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
                $routes->post($path . '/{' . $resourceId . '}/relationships/' . $field->getDisplayName(), $controller . '@addToRelationship')
                    ->summary(function () use ($field, $resourceDefinition) {
                        $entityName = $resourceDefinition->getEntityName(false);

                        $relatedEntityName = $field->getChildResourceDefinition()->getEntityName(true);
                        return 'Add ' . $field->getDisplayName() . ' ' . $relatedEntityName . ' to a ' . $entityName;
                    })
                    ->parameters()->path($resourceId)->string()->required()
                    ->parameters()->resource(get_class($field->getChildResourceDefinition()))->setAction(Action::IDENTIFIER)->many()
                    ->returns()->statusCode(200)->one(get_class($field->getResourceDefinition()));

                $routes->delete($path . '/{' . $resourceId . '}/relationships/' . $field->getDisplayName(), $controller . '@addToRelationship')
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

    public function viewRelationship($resourceId, $relationshipDisplayName)
    {

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
        return new Context($action, $parameters);
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
            $context->showFields(array_map('trim', explode(',', $toShow)));
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
            new ResourceFactory(
                JsonApiResource::class,
                JsonApiResourceCollection::class
            )
        );
    }
}
