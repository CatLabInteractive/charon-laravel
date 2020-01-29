<?php

namespace CatLab\Charon\Laravel\Processors;

use CatLab\Base\Interfaces\Database\SelectQueryParameters;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\Processor;
use CatLab\Charon\Interfaces\ResourceCollection;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Interfaces\ResourceTransformer;
use CatLab\Charon\Interfaces\RESTResource;
use CatLab\Charon\Models\FilterResults;
use CatLab\Charon\Models\Values\Base\RelationshipValue;
use CatLab\Laravel\Database\SelectQueryTransformer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class PaginationProcessor
 * @package CatLab\Charon\Laravel\Processors
 */
class PaginationProcessor extends \CatLab\Charon\Processors\PaginationProcessor
{
    /**
     * @inheritDoc
     */
    /*
    public function processFilters(
        ResourceTransformer $transformer,
        $queryBuilder,
        $request,
        ResourceDefinition $definition,
        Context $context,
        FilterResults $filterResults
    ) {
        $requestResolver = $transformer->getRequestResolver();

        // the amount of records we want.
        $records = $requestResolver->getRecords($request);
        if ($records < 1) {
            $records = config('eukles.default_records', 10);
        }

        // get the page we want
        $page = $requestResolver->getPage($request);
        if ($page < 1) {
            $page = 1;
        }

        // First count the total amount of records
        $totalAmountOfRecords = $queryBuilder->count();
        $filterResults->setTotalRecords($totalAmountOfRecords);

        $filterResults->setCurrentPage($page);
        $filterResults->setRecords($records);

        // now make the final selection
        $queryBuilder->skip(($page - 1) * $records)->limit($records);
    }*/

    /**
     * @inheritDoc
     */
    public function processCollection(
        ResourceTransformer $transformer,
        ResourceCollection $collection,
        $definition,
        Context $context,
        FilterResults $filterResults = null,
        RelationshipValue $parent = null,
        $parentEntity = null
    ) {
        list ($url, $cursor) = $this->prepareCursor($transformer, $collection, $definition, $context, $filterResults, $parent, $parentEntity);

        if ($cursor) {
            $collection->addMeta('links', [
                'next' => $cursor->getNext() ? $url . '?' . http_build_query($cursor->getNext()) : null,
                'previous' => $cursor->getPrevious() ? $url . '?' . http_build_query($cursor->getPrevious()) : null
            ]);
        }

        if ($filterResults) {
            $collection->addMeta('page', [
                'total' => $filterResults->getTotalRecords(),
                'per-page' => $filterResults->getRecords(),
                'current-page' => $filterResults->getCurrentPage(),
                'last-page' => ceil($filterResults->getTotalRecords() / $filterResults->getRecords())
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    public function processResource(
        ResourceTransformer $transformer,
        RESTResource $resource,
        ResourceDefinition $definition,
        Context $context,
        RelationshipValue $parent = null,
        $parentEntity = null
    ) {
        // TODO: Implement processResource() method.
    }
}
