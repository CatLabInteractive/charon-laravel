<?php

declare(strict_types=1);

namespace Tests;

use CatLab\Charon\Laravel\Database\Model as CharonModel;
use CatLab\Charon\Laravel\Resolvers\PropertySetter;
use CatLab\Charon\Models\ResourceDefinition;
use CatLab\Charon\Validation\ResourceDefinitionValidator;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * The Laravel setter can edit children of a CatLab\Charon\Laravel\Database\Model
 * without an edit<Name>() method, so the definition validator must not flag
 * those - while still flagging a plain Eloquent model, which is the default an
 * application reaches for and which cannot.
 */
final class SupportsChildEditingTest extends BaseTest
{
    private function validate(string $entityClassName): array
    {
        $definition = new ResourceDefinition($entityClassName);
        $definition->relationship('child', ResourceDefinition::class)
            ->one()
            ->writeable(true, true)
            ->visible(true, true);

        return (new ResourceDefinitionValidator(new PropertySetter()))->validate($definition);
    }

    public function testCharonModelCanEditChildren(): void
    {
        $this->assertSame([], $this->validate(ChildEditingCharonModel::class));
    }

    public function testPlainEloquentModelCannot(): void
    {
        $problems = $this->validate(ChildEditingEloquentModel::class);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('linkable()', $problems[0]);
    }

    public function testPlainEloquentModelWithAnEditMethodCan(): void
    {
        $this->assertSame([], $this->validate(ChildEditingEloquentModelWithEditor::class));
    }
}

class ChildEditingCharonModel extends CharonModel
{
}

class ChildEditingEloquentModel extends EloquentModel
{
}

class ChildEditingEloquentModelWithEditor extends EloquentModel
{
    public function editChild($children)
    {
    }
}
