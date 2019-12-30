<?php

namespace Tests\ResourceDefinitionDepths;

use Tests\Models\MockEntityModel;

/**
 * Class MockResourceDefinitionDepthFour
 * @package Tests\Models
 */
class MockResourceDefinitionDepthFour extends \CatLab\Charon\Models\ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(MockEntityModel::class);

        $this
            ->identifier('id')

            ->relationship('children', MockResourceDefinitionDepthFour::class)
                ->expanded()
                ->visible(true, true)
                ->many()
                ->maxDepth(4)
                ->writeable()
        ;
    }
}
