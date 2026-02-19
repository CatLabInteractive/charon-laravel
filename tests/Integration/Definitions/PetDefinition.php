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

            ->relationship('tags', TagDefinition::class)
                ->many()
                ->visible(true, true)
                ->expandable()
                ->writeable()
        ;
    }
}
