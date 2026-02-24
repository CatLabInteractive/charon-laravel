<?php

namespace Tests\Integration\Controllers;

use CatLab\Charon\Laravel\Controllers\ChildCrudController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Tests\Integration\Definitions\PetDefinition;
use Tests\Integration\Models\Store;

class StorePetController extends BaseResourceController
{
    use ChildCrudController {
        ChildCrudController::__construct as charonConstruct;
    }

    const RESOURCE_DEFINITION = PetDefinition::class;

    public function __construct()
    {
        $this->charonConstruct();
    }

    public function getRelationship(Request $request): Relation
    {
        return $this->getParent($request)->pets();
    }

    public function getParent(Request $request): Model
    {
        return Store::findOrFail($request->route('store'));
    }

    public function getRelationshipKey(): string
    {
        return 'store';
    }

    protected function authorizeIndex(Request $request, ...$args)
    {
        // No authorization for tests
    }

    protected function authorizeCreate(Request $request)
    {
        // No authorization for tests
    }

    protected function authorizeView(Request $request, $entity)
    {
        // No authorization for tests
    }

    protected function authorizeEdit(Request $request, $entity)
    {
        // No authorization for tests
    }

    protected function authorizeDestroy(Request $request, $entity)
    {
        // No authorization for tests
    }
}
