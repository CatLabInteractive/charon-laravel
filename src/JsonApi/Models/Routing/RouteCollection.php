<?php

namespace CatLab\Charon\Laravel\JsonApi\Models\Routing;

use CatLab\Charon\Models\StaticResourceDefinitionFactory;

/**
 * Class RouteCollection
 * @package CatLab\Charon\Laravel\JsonApi\Models\Routing
 */
class RouteCollection extends \CatLab\Charon\Collections\RouteCollection
{
    /**
     * @param $method
     * @param $path
     * @param $action
     * @param $options
     * @return Route
     */
    protected function createRoute($method, $path, $action, $options)
    {
        return new Route($this, $method, $path, $action, $options);
    }

    /**
     * @inheritDoc
     */
    public function resource($resourceDefinition, $path, $controller, $options)
    {
        $group = parent::resource($resourceDefinition, $path, $controller, $options);

        $only = $options[self::OPTIONS_ONLY_INCLUDE_METHODS] ?? [
            self::OPTIONS_METHOD_INDEX,
            self::OPTIONS_METHOD_VIEW,
            self::OPTIONS_METHOD_STORE,
            self::OPTIONS_METHOD_EDIT,
            self::OPTIONS_METHOD_DESTROY
        ];

        if (in_array(self::OPTIONS_METHOD_DESTROY, $only)) {
            $resourceDefinitionFactory = StaticResourceDefinitionFactory::getFactoryOrDefaultFactory($resourceDefinition);
            $group->delete($path, $controller . '@bulkDestroy', [], 'bulkDestroy')
                ->summary(function () use ($resourceDefinitionFactory) {
                    $entityName = $resourceDefinitionFactory->getDefault()->getEntityName(true);
                    return 'Bulk delete ' . $entityName;
                });
        }

        return $group;
    }

    /**
     * @inheritDoc
     */
    public function childResource($resourceDefinition, $parentPath, $childPath, $controller, $options)
    {
        $group = parent::childResource($resourceDefinition, $parentPath, $childPath, $controller, $options);

        $only = $options[self::OPTIONS_ONLY_INCLUDE_METHODS] ?? [
            self::OPTIONS_METHOD_INDEX,
            self::OPTIONS_METHOD_VIEW,
            self::OPTIONS_METHOD_STORE,
            self::OPTIONS_METHOD_EDIT,
            self::OPTIONS_METHOD_DESTROY
        ];

        if (in_array(self::OPTIONS_METHOD_DESTROY, $only)) {
            $parentId = $options[self::OPTIONS_PARENT_IDENTIFIER_NAME] ?? 'parentId';
            $resourceDefinitionFactory = StaticResourceDefinitionFactory::getFactoryOrDefaultFactory($resourceDefinition);

            $bulkDestroyRoute = $group->delete($parentPath, $controller . '@bulkDestroy', [], 'bulkDestroy')
                ->summary(function () use ($resourceDefinitionFactory) {
                    $entityName = $resourceDefinitionFactory->getDefault()->getEntityName(true);
                    return 'Bulk delete ' . $entityName;
                });

            $bulkDestroyRoute->parameters()->path($parentId)->string()->required();
        }

        return $group;
    }
}
