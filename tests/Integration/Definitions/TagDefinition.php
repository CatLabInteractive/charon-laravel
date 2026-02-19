<?php

namespace Tests\Integration\Definitions;

use CatLab\Charon\Models\ResourceDefinition;
use Tests\Integration\Models\Tag;

class TagDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Tag::class);

        $this
            ->identifier('id')
                ->int()

            ->field('name')
                ->string()
                ->required()
                ->writeable()
                ->visible(true, true)

            ->relationship('metadata', TagMetadataDefinition::class)
                ->many()
                ->visible(true, true)
                ->expandable()
                ->writeable()
        ;
    }
}
