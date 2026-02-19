<?php

namespace Tests\Integration\Definitions;

use CatLab\Charon\Models\ResourceDefinition;
use Tests\Integration\Models\TagMetadata;

class TagMetadataDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(TagMetadata::class);

        $this
            ->identifier('id')
                ->int()

            ->field('key')
                ->string()
                ->required()
                ->writeable()
                ->visible(true, true)

            ->field('value')
                ->string()
                ->writeable()
                ->visible(true, true)
        ;
    }
}
