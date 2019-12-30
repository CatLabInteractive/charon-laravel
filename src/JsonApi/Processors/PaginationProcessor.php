<?php

namespace CatLab\Charon\Laravel\JsonApi\Processors;

use CatLab\Charon\Interfaces\ResourceCollection;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceTransformer;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Models\FilterResults;
use CatLab\Charon\Models\Values\Base\RelationshipValue;

/**
 * Class PaginationProcessor
 * @package CatLab\Charon\Laravel\JsonApi\Processors
 */
class PaginationProcessor extends \CatLab\Charon\Processors\PaginationProcessor
{
    /**
     * @param ResourceTransformer $transformer
     * @param ResourceCollection $collection
     * @param ResourceDefinition $definition
     * @param Context $context
     * @param FilterResults|null $filterResults
     * @param RelationshipValue|null $parent
     * @param null $parentEntity
     */
    public function processCollection(
        ResourceTransformer $transformer,
        ResourceCollection $collection,
        ResourceDefinition $definition,
        Context $context,
        FilterResults $filterResults = null,
        RelationshipValue $parent = null,
        $parentEntity = null
    ) {
        list ($url, $cursor) = $this->prepareCursor($transformer, $collection, $definition, $context, $filterResults, $parent, $parentEntity);

        $collection->addMeta('links', [
            'next' => $cursor->getNext() ? $url . '?' . http_build_query($cursor->getNext()) : null,
            'previous' => $cursor->getPrevious() ? $url . '?' . http_build_query($cursor->getPrevious()) : null
        ]);

        if ($filterResults) {
            $collection->addMeta('page', [
                'total' => $filterResults->getTotalRecords(),
                'per-page' => $filterResults->getRecords(),
                'current-page' => $filterResults->getCurrentPage(),
                'cursors' => $cursor->toArray()
            ]);
        }
    }
}
