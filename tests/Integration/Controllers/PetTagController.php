<?php

namespace Tests\Integration\Controllers;

use CatLab\Charon\Laravel\Controllers\ChildCrudController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Tests\Integration\Definitions\TagDefinition;
use Tests\Integration\Models\Pet;

class PetTagController extends BaseResourceController
{
    use ChildCrudController {
        ChildCrudController::__construct as charonConstruct;
    }

    const RESOURCE_DEFINITION = TagDefinition::class;

    public function __construct()
    {
        $this->charonConstruct();
    }

    public function getRelationship(Request $request): Relation
    {
        return $this->getParent($request)->tags();
    }

    public function getParent(Request $request): Model
    {
        return Pet::findOrFail($request->route('pet'));
    }

    public function getRelationshipKey(): string
    {
        return 'pet';
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
