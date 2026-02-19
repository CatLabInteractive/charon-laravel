<?php

namespace Tests;

use CatLab\Charon\Collections\RouteCollection;
use Tests\Petstore\Definitions\PetDefinition;

/**
 * Class BulkOperationsTest
 * @package Tests
 */
class BulkOperationsTest extends BaseTest
{
    /**
     * Test that resource() registers a bulk destroy route when destroy is enabled.
     */
    public function testResourceRouteIncludesBulkDestroy()
    {
        $routes = new RouteCollection();
        $group = $routes->resource(
            PetDefinition::class,
            '/pets',
            'PetController',
            [
                'only' => ['index', 'view', 'store', 'edit', 'destroy']
            ]
        );

        $allRoutes = $group->getRoutes();
        $bulkDestroyRoute = null;
        foreach ($allRoutes as $route) {
            if ($route->getAction() === 'PetController@bulkDestroy') {
                $bulkDestroyRoute = $route;
                break;
            }
        }

        $this->assertNotNull($bulkDestroyRoute, 'Bulk destroy route should be registered');
        $this->assertEquals('delete', $bulkDestroyRoute->getMethod());
        $this->assertEquals('/pets', $bulkDestroyRoute->getPath());
    }

    /**
     * Test that resource() does NOT register a bulk destroy route when destroy is not in only.
     */
    public function testResourceRouteExcludesBulkDestroyWhenDestroyNotInOnly()
    {
        $routes = new RouteCollection();
        $group = $routes->resource(
            PetDefinition::class,
            '/pets',
            'PetController',
            [
                'only' => ['index', 'view', 'store']
            ]
        );

        $allRoutes = $group->getRoutes();
        foreach ($allRoutes as $route) {
            $this->assertNotEquals(
                'PetController@bulkDestroy',
                $route->getAction(),
                'Bulk destroy route should NOT be registered when destroy is not in only'
            );
        }
    }

    /**
     * Test that childResource() registers a bulk destroy route when destroy is enabled.
     */
    public function testChildResourceRouteIncludesBulkDestroy()
    {
        $routes = new RouteCollection();
        $group = $routes->childResource(
            PetDefinition::class,
            '/owners/{parentId}/pets',
            '/owners/{parentId}/pets',
            'PetController',
            [
                'only' => ['index', 'view', 'store', 'edit', 'destroy']
            ]
        );

        $allRoutes = $group->getRoutes();
        $bulkDestroyRoute = null;
        foreach ($allRoutes as $route) {
            if ($route->getAction() === 'PetController@bulkDestroy') {
                $bulkDestroyRoute = $route;
                break;
            }
        }

        $this->assertNotNull($bulkDestroyRoute, 'Bulk destroy route should be registered for child resource');
        $this->assertEquals('delete', $bulkDestroyRoute->getMethod());
        $this->assertEquals('/owners/{parentId}/pets', $bulkDestroyRoute->getPath());
    }

    /**
     * Test that childResource() does NOT register a bulk destroy route when destroy is not in only.
     */
    public function testChildResourceRouteExcludesBulkDestroyWhenDestroyNotInOnly()
    {
        $routes = new RouteCollection();
        $group = $routes->childResource(
            PetDefinition::class,
            '/owners/{parentId}/pets',
            '/owners/{parentId}/pets',
            'PetController',
            [
                'only' => ['index', 'view', 'store']
            ]
        );

        $allRoutes = $group->getRoutes();
        foreach ($allRoutes as $route) {
            $this->assertNotEquals(
                'PetController@bulkDestroy',
                $route->getAction(),
                'Bulk destroy route should NOT be registered when destroy is not in only'
            );
        }
    }

    /**
     * Test that resource() with default options includes bulk destroy.
     */
    public function testResourceRouteDefaultOptionsIncludesBulkDestroy()
    {
        $routes = new RouteCollection();
        $group = $routes->resource(
            PetDefinition::class,
            '/pets',
            'PetController',
            []
        );

        $allRoutes = $group->getRoutes();
        $bulkDestroyRoute = null;
        foreach ($allRoutes as $route) {
            if ($route->getAction() === 'PetController@bulkDestroy') {
                $bulkDestroyRoute = $route;
                break;
            }
        }

        $this->assertNotNull($bulkDestroyRoute, 'Bulk destroy route should be registered with default options');
    }

    /**
     * Test that the bulk destroy route is accessible by name.
     */
    public function testBulkDestroyRouteAccessibleByName()
    {
        $routes = new RouteCollection();
        $group = $routes->resource(
            PetDefinition::class,
            '/pets',
            'PetController',
            []
        );

        $this->assertTrue(isset($group['bulkDestroy']), 'Bulk destroy route should be accessible by name');
        $this->assertEquals('PetController@bulkDestroy', $group['bulkDestroy']->getAction());
    }
}
