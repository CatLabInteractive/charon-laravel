<?php

namespace Tests\Integration\Definitions;

use CatLab\Charon\Models\ResourceDefinition;
use Tests\Integration\Models\Pet;

class PetDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Pet::class);

        $this
            ->identifier('id')
                ->int()

            ->field('name')
                ->string()
                ->required()
                ->writeable()
                ->visible(true, true)

            ->field('status')
                ->string()
                ->writeable()
                ->visible(true, true)

            ->relationship('store', StoreDefinition::class)
                ->one()
                ->visible(true, true)
                ->expandable()
                ->linkable()

            ->relationship('tags', TagDefinition::class)
                ->many()
                ->visible(true, true)
                ->expandable()
                ->writeable()

            // Self-referencing, link-only relationship: mirrors eukles' WorkflowStep
            // 'linkWithRole:next' shape (single linkable "one" relationship on the
            // child's own definition). Used to exercise sibling-to-sibling client
            // references ('$ref') in bulk writes.
            ->relationship('linkedPet', PetDefinition::class)
                ->one()
                ->visible(true, true)
                ->linkable()

            // Same shape, "many" cardinality: a self-referencing, link-only
            // many-to-many relationship, used to exercise the
            // Cardinality::MANY client-reference drain branch
            // (PropertySetter::addChildren() -> BelongsToMany pivot).
            ->relationship('relatedPets', PetDefinition::class)
                ->many()
                ->visible(true, true)
                ->linkable()
        ;
    }
}
