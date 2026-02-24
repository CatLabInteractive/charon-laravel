<?php

namespace Tests\Integration\Definitions;

use CatLab\Charon\Models\ResourceDefinition;
use Tests\Integration\Models\Store;

class StoreDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Store::class);

        $this
            ->identifier('id')
                ->int()

            ->field('name')
                ->string()
                ->required()
                ->writeable()
                ->visible(true, true)

            ->field('address')
                ->string()
                ->writeable()
                ->visible(true, true)

            ->relationship('pets', PetDefinition::class)
                ->many()
                ->visible(true, true)
                ->expandable()
        ;
    }
}
