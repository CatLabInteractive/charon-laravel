<?php

namespace Tests\Integration\Controllers;

use CatLab\Charon\Laravel\Controllers\CrudController;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Integration\Definitions\StoreDefinition;

class StoreController extends BaseResourceController
{
    use CrudController {
        CrudController::__construct as charonConstruct;
    }

    const RESOURCE_DEFINITION = StoreDefinition::class;

    public function __construct()
    {
        $this->charonConstruct();
    }

    protected function authorizeIndex(\Illuminate\Http\Request $request, ...$args)
    {
        // No authorization for tests
    }

    protected function authorizeCreate(\Illuminate\Http\Request $request)
    {
        // No authorization for tests
    }

    protected function authorizeView(\Illuminate\Http\Request $request, $entity)
    {
        // No authorization for tests
    }

    protected function authorizeEdit(\Illuminate\Http\Request $request, $entity)
    {
        // No authorization for tests
    }

    protected function authorizeDestroy(\Illuminate\Http\Request $request, $entity)
    {
        // No authorization for tests
    }
}
