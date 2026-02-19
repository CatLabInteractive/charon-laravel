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
     * Test that default resource routes do not include bulk destroy
     * (bulk destroy route registration is deferred to upstream Charon RouteCollection).
     */
    public function testResourceRouteDoesNotIncludeBulkDestroyYet()
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
        foreach ($allRoutes as $route) {
            $this->assertNotEquals(
                'PetController@bulkDestroy',
                $route->getAction(),
                'Bulk destroy route registration is deferred to upstream Charon RouteCollection'
            );
        }
    }

    /**
     * Test that default childResource routes do not include bulk destroy
     * (bulk destroy route registration is deferred to upstream Charon RouteCollection).
     */
    public function testChildResourceRouteDoesNotIncludeBulkDestroyYet()
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
        foreach ($allRoutes as $route) {
            $this->assertNotEquals(
                'PetController@bulkDestroy',
                $route->getAction(),
                'Bulk destroy route registration is deferred to upstream Charon RouteCollection'
            );
        }
    }
}
