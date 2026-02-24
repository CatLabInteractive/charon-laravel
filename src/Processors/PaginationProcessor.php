<?php

namespace CatLab\Charon\Laravel\Processors;

use CatLab\Base\Interfaces\Database\SelectQueryParameters;
use CatLab\Base\Interfaces\Database\WhereParameter;
use CatLab\Base\Interfaces\Grammar\AndConjunction;
use CatLab\Base\Interfaces\Grammar\OrConjunction;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceCollection;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Interfaces\ResourceDefinitionFactory;
use CatLab\Charon\Interfaces\ResourceTransformer;
use CatLab\Charon\Models\FilterResults;
use CatLab\Charon\Models\Properties\Base\Field;
use CatLab\Charon\Models\Values\Base\RelationshipValue;

/**
 * Class PaginationProcessor
 * @package CatLab\Charon\Laravel\Processors
 */
class PaginationProcessor extends \CatLab\Charon\Processors\PaginationProcessor
{
    /**
     * @inheritDoc
     */
    public function processCollection(
        ResourceTransformer $transformer,
        ResourceCollection $collection,
        ResourceDefinitionFactory $definition,
        Context $context,
        FilterResults $filterResults = null,
        RelationshipValue $parent = null,
        $parentEntity = null
    ): void {
        list ($url, $cursor) = $this->prepareCursor(
            $transformer,
            $collection,
            $definition->getDefault(),
            $context,
            $filterResults,
            $parent,
            $parentEntity
        );

        if ($cursor) {
            $collection->addMeta('links', [
                'next' => $cursor->getNext() ? $url . '?' . http_build_query($cursor->getNext()) : null,
                'previous' => $cursor->getPrevious() ? $url . '?' . http_build_query($cursor->getPrevious()) : null
            ]);

            $collection->addMeta('pagination', [
                'cursors' => $cursor->toArray(),
                'next' => $cursor->getNext() ? $url . '?' . http_build_query($cursor->getNext()) : null,
                'previous' => $cursor->getPrevious() ? $url . '?' . http_build_query($cursor->getPrevious()) : null
            ]);
        }

        if ($filterResults) {
            $collection->addMeta('page', [
                'total' => $filterResults->getTotalRecords(),
                'per-page' => $filterResults->getRecords(),
                'current-page' => $filterResults->getCurrentPage(),
                'last-page' => intval(ceil($filterResults->getTotalRecords() / $filterResults->getRecords()))
            ]);
        }
    }

    /**
     * @param ResourceTransformer $transformer
     * @param Context $context
     * @param ResourceDefinition $resourceDefinition
     * @param SelectQueryParameters $filter
     * @param null $queryBuilder
     * @param FilterResults $filterResults
     */
    protected function processProcessorFilters(
        ResourceTransformer $transformer,
        Context $context,
        ResourceDefinition $resourceDefinition,
        SelectQueryParameters $filter,
        $queryBuilder,
        FilterResults $filterResults
    ) {
        $this->processWhereFilters(
            $transformer,
            $context,
            $resourceDefinition,
            $filter->getWhere(),
            $queryBuilder
        );

        // now we need to translate these to our own system
        foreach ($filter->getSort() as $sort) {
            $field = $sort->getEntity();
            if ($field instanceof Field) {
                $field->setRequiredForProcessor();

                $transformer->getQueryAdapter()->applyPropertySorting(
                    $transformer,
                    $field->getResourceDefinition(),
                    $context,
                    $field,
                    $queryBuilder,
                    $sort->getDirection()
                );
            } else {
                throw new \InvalidArgumentException(
                    'WhereParameter requires a Field to be set as entity; ' . get_class($field) . ' provided.'
                );
            }
        }

        $limit = $filter->getLimit();
        if ($limit) {
            $transformer->getQueryAdapter()->applyLimit(
                $transformer,
                $resourceDefinition,
                $context,
                $queryBuilder,
                $limit->getAmount(),
                $limit->getOffset()
            );
        }

        $filterResults->setReversed($filter->isReverse());
    }

    /**
     * @param ResourceTransformer $transformer
     * @param Context $context
     * @param ResourceDefinition $resourceDefinition
     * @param WhereParameter[] $whereParameters
     * @param \Illuminate\Database\Query\Builder $queryBuilder
     */
    private function processWhereFilters(
        ResourceTransformer $transformer,
        Context $context,
        ResourceDefinition $resourceDefinition,
        $whereParameters,
        $queryBuilder
    ) {
        foreach ($whereParameters as $whereParameter) {
            if ($comparison = $whereParameter->getComparison()) {

                $field = $comparison->getEntity();
                if ($field instanceof Field) {
                    $transformer->getQueryAdapter()->applyPropertyFilter(
                        $transformer,
                        $field->getResourceDefinition(),
                        $context,
                        $field,
                        $queryBuilder,
                        $comparison->getValue(),
                        $comparison->getOperator()
                    );
                } else {
                    $entityType = is_object($field) ? get_class($field) : gettype($field);
                    throw new \InvalidArgumentException(
                        'WhereParameter requires a Field to be set as entity; ' . $entityType . ' provided.'
                    );
                }
            }

            // Won't somebody please think of the children?!
            foreach ($whereParameter->getChildren() as $child) {
                if ($child instanceof AndConjunction) {
                    $queryBuilder->where(function ($query) use ($transformer, $context, $resourceDefinition, $child) {
                        $this->processWhereFilters($transformer, $context, $resourceDefinition, [$child->getSubject()], $query);
                    });
                } elseif ($child instanceof OrConjunction) {
                    $queryBuilder->orWhere(function ($query) use ($transformer, $context, $resourceDefinition, $child) {
                        $this->processWhereFilters($transformer, $context, $resourceDefinition, [$child->getSubject()], $query);
                    });
                } else {
                    throw new \InvalidArgumentException("Got an unknown conjunction");
                }
            }
        }
    }
}
