<?php

namespace CatLab\Charon\Laravel\JsonApi\Models\Routing;

use CatLab\Charon\Interfaces\ResourceTransformer;
use CatLab\Charon\Laravel\JsonApi\Resolvers\JsonApiRequestResolver;
use CatLab\Charon\Models\Properties\Base\Field;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\Charon\Models\Routing\Parameters\Base\Parameter;
use CatLab\Charon\Models\Routing\Parameters\QueryParameter;
use CatLab\Requirements\InArray;

/**
 * Class Route
 * @package CatLab\Charon\Laravel\JsonApi\Models\Routing
 */
class Route extends \CatLab\Charon\Models\Routing\Route
{
    /**
     * @param bool TRUE if at least one return value consists of multiple models.
     * @return Parameter[]
     * @throws \CatLab\Charon\Exceptions\InvalidScalarException
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    public function getExtraParameters($hasCardinalityMany)
    {
        $returnValues = $this->getReturnValues();

        $sortValues = [];
        $expandValues = [];
        $selectValues = [];
        $visibleValues = [];

        $parameters = [];

        foreach ($returnValues as $returnValue) {

            // Look for sortable fields
            foreach ($returnValue->getResourceDefinitions() as $resourceDefinition) {

                foreach ($resourceDefinition->getFields() as $field) {

                    /** @var Field $field */

                    // Sortable field
                    if ($field->isSortable() && $hasCardinalityMany) {
                        $sortValues[] = $field->getDisplayName();
                        $sortValues[] = '-' . $field->getDisplayName();
                    }

                    // Visible
                    if ($field->isViewable($returnValue->getContext())) {
                        $visibleValues[] = $field->getDisplayName();
                    }

                    // Expandable field
                    if ($field instanceof RelationshipField) {
                        $this->addExpandableValues($field, $returnValue->getContext(), $visibleValues, $expandValues);
                    }

                    // Filterable fields
                    if ($field->isFilterable() && $hasCardinalityMany) {
                        $parameters[] = $this->getFilterField($field);
                    }

                    // Searchable fields
                    if ($field->isSearchable() && $hasCardinalityMany) {
                        $parameters[] = $this->getSearchField($field);
                    }

                    $selectValues[] = $field->getDisplayName();
                }
            }
        }

        if (count($sortValues) > 0) {
            $parameters[] = (new QueryParameter(ResourceTransformer::SORT_PARAMETER))
                ->setType('string')
                ->enum($sortValues)
                ->allowMultiple()
                ->describe('Define the sort parameter. Separate multiple values with comma.')
            ;
        }

        if (count($expandValues) > 0) {
            $parameters[] = (new QueryParameter('include'))
                ->setType('string')
                ->enum($expandValues)
                ->allowMultiple()
                ->describe('Include related entities. Values: '
                    . implode(', ', $expandValues))
            ;
        }

        if (count($visibleValues) > 0) {

            // Add asterisk
            array_unshift($visibleValues, '*');

            $parameters[] = (new QueryParameter(ResourceTransformer::FIELDS_PARAMETER))
                ->setType('string')
                ->enum($visibleValues)
                ->allowMultiple()
                ->describe('Define fields to return. Separate multiple values with comma. Values: '
                    . implode(', ', $visibleValues))
            ;
        }

        return $parameters;
    }

    /**
     * @param Field $field
     * @return Parameter
     * @throws \CatLab\Charon\Exceptions\InvalidScalarException
     */
    protected function getFilterField(Field $field)
    {
        $filter = (new QueryParameter(JsonApiRequestResolver::FILTER_PARAMETER . '[' . $field->getDisplayName() . ']'))
            ->setType($field->getType())
            ->describe('Filter results on ' . $field->getDisplayName());

        // Check for applicable requirements
        foreach ($field->getRequirements() as $requirement) {
            if ($requirement instanceof InArray) {
                $filter->enum($requirement->getValues());
            }
        }

        return $filter;
    }

    /**
     * @param Field $field
     * @return Parameter
     * @throws \CatLab\Charon\Exceptions\InvalidScalarException
     */
    protected function getSearchField(Field $field)
    {
        $filter = (new QueryParameter(JsonApiRequestResolver::SEARCH_PARAMETER . '[' . $field->getDisplayName() . ']'))
            ->setType($field->getType())
            ->describe('Search results on ' . $field->getDisplayName());

        // Check for applicable requirements
        foreach ($field->getRequirements() as $requirement) {
            if ($requirement instanceof InArray) {
                $filter->enum($requirement->getValues());
            }
        }

        return $filter;
    }
}
