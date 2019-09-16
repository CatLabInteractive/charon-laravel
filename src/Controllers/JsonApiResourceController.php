<?php


namespace CatLab\Charon\Laravel\Controllers;

use CatLab\Base\Helpers\ArrayHelper;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\InputParsers\JsonBodyInputParser;
use CatLab\Charon\Laravel\InputParsers\PostInputParser;
use CatLab\Charon\Laravel\Models\JsonApiResponse;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use CatLab\Charon\Laravel\Resolvers\JsonApiRequestResolver;
use CatLab\Charon\Laravel\Resolvers\PropertyResolver;
use CatLab\Charon\Laravel\Resolvers\PropertySetter;
use CatLab\Charon\Laravel\Transformers\ResourceTransformer;
use CatLab\Charon\Models\Context;
use CatLab\Charon\Pagination\PaginationBuilder;
use CatLab\Charon\Processors\PaginationProcessor;
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
    use ResourceController;

    /**
     * @param $data
     * @param \CatLab\Charon\Interfaces\Context|null $context
     * @return ResourceResponse
     */
    protected function getResourceResponse($data, Context $context  = null)
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
     * @param null $resourceDefinition
     * @return \Illuminate\Http\JsonResponse
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    protected function outputList($models, array $parameters = [], $resourceDefinition = null)
    {
        $resourceDefinition = $resourceDefinition ?? $this->resourceDefinition;

        $context = $this->getContext(Action::INDEX, $parameters);

        $models = $this->filterAndGet(
            $models,
            $resourceDefinition,
            $context,
            Request::input('records', 10)
        );

        $output = $this->modelsToResources($models, $context, $resourceDefinition);
        return Response::json($output);
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
     * @return Context|string
     */
    protected function getContext($action = Action::VIEW, $parameters = []): \CatLab\Charon\Interfaces\Context
    {
        $context = new Context($action, $parameters);

        if ($toShow = Request::query('fields')) {
            $context->showFields(array_map('trim', explode(',', $toShow)));
        }

        if ($toExpand = Request::query('include')) {
            $context->expandFields(array_map('trim', explode(',', $toExpand)));
        }

        $context->addProcessor(new PaginationProcessor(PaginationBuilder::class));

        $context->addInputParser(JsonBodyInputParser::class);
        $context->addInputParser(PostInputParser::class);

        $context->setUrl(Request::url());

        return $context;
    }

    /**
     * @param ResourceValidationException $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getValidationErrorResponse(ResourceValidationException $e)
    {
        return Response::json([
            'error' => [
                'message' => 'Could not decode resource.',
                'issues' => $e->getMessages()->toMap()
            ]
        ])->setStatusCode(400);
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
            new JsonApiRequestResolver()
        );
    }
}
