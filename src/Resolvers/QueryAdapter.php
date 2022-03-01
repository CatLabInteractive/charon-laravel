<?php

namespace CatLab\Charon\Laravel\Resolvers;

use CatLab\Base\Enum\Operator;
use CatLab\Charon\Collections\PropertyValueCollection;
use CatLab\Charon\Exceptions\InvalidPropertyException;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Interfaces\ResourceTransformer;
use CatLab\Charon\Models\Identifier;
use CatLab\Charon\Models\Properties\Base\Field;
use CatLab\Charon\Models\Properties\RelationshipField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Class QueryAdapter
 * @package CatLab\Charon\Laravel\Resolvers
 */
class QueryAdapter extends \CatLab\Charon\Resolvers\QueryAdapter
{
    /**
     * @param ResourceTransformer $transformer
     * @param RelationshipField $field
     * @param mixed $parentEntity
     * @param Identifier $identifier
     * @param Context $context
     * @return mixed
     * @throws InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    public function getChildByIdentifiers(
        ResourceTransformer $transformer,
        RelationshipField $field,
        $parentEntity,
        Identifier $identifier,
        Context $context
    ) {
        $identifiers = $identifier->getIdentifiers();

        $entities = $transformer
            ->getPropertyResolver()
            ->resolveProperty($transformer, $parentEntity, $field, $context);

        if ($entities instanceof Relation) {
            // Clone to avoid setting multiple filters
            $entities = clone $entities;
            foreach ($identifiers as $k => $v) {
                $entities->where($this->getQualifiedName($k), $v->getValue());
            }

            $entity = $entities->get()->first();
            if (!$entity) {
                return null;
            }
            return $entity;
        }

        foreach ($entities as $entity) {
            if ($this->entityEquals($transformer, $entity, $identifier, $context)) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * @param Field $field
     * @return string
     */
    public function getQualifiedName(Field $field)
    {
        $name = $field->getResourceDefinition()->getEntityClassName();

        /** @var Model $obj */
        $obj = new $name;

        return $obj->getTable() . '.' . $field->getName();
    }

    /**
     * @inheritDoc
     */
    protected function applySimpleWhere(
        ResourceTransformer $transformer,
        ResourceDefinition $definition,
        Context $context,
        Field $field,
        $queryBuilder,
        $value,
        $operator = Operator::EQ
    ) {
        $queryBuilder = $this->checkValidQueryBuilder($queryBuilder);

        if ($operator === Operator::SEARCH) {
            $queryBuilder->where($this->getQualifiedName($field), 'LIKE', '%' . $value . '%');
        } else {
            $queryBuilder->where($this->getQualifiedName($field), $operator, $value);
        }
    }

    /**
     * @inheritDoc
     */
    protected function applySimpleSorting(
        ResourceTransformer $transformer,
        ResourceDefinition $definition,
        Context $context,
        Field $field,
        $queryBuilder,
        $direction = 'asc'
    ) {
        $queryBuilder = $this->checkValidQueryBuilder($queryBuilder);
        $queryBuilder->orderBy($this->getQualifiedName($field), $direction);
    }

    /**
     * @param ResourceTransformer $transformer
     * @param ResourceDefinition $definition
     * @param Context $context
     * @param $queryBuilder
     * @return
     */
    public function countRecords(
        ResourceTransformer $transformer,
        ResourceDefinition $definition,
        Context $context,
        $queryBuilder
    ) {
        //$queryBuilder = $this->checkValidQueryBuilder($queryBuilder);
        return $queryBuilder->count();
    }

    /**
     * @param ResourceTransformer $transformer
     * @param ResourceDefinition $definition
     * @param Context $context
     * @param $queryBuilder
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecords(
        ResourceTransformer $transformer,
        ResourceDefinition $definition,
        Context $context,
        $queryBuilder
    ) {
        if ($queryBuilder instanceof Collection) {
            return $queryBuilder;
        }

        //$queryBuilder = $this->checkValidQueryBuilder($queryBuilder);
        if (
            $queryBuilder instanceof \Illuminate\Database\Eloquent\Builder ||
            $queryBuilder instanceof \Illuminate\Database\Query\Builder ||
            $queryBuilder instanceof \Illuminate\Database\Eloquent\Relations\Relation
        ) {
            return $queryBuilder->get();
        }

        return $queryBuilder;
    }

    /**
     * @param ResourceTransformer $transformer
     * @param ResourceDefinition $definition
     * @param Context $context
     * @param $queryBuilder
     * @param $records
     * @param $skip
     */
    public function applyLimit(
        ResourceTransformer $transformer,
        ResourceDefinition $definition,
        Context $context,
        $queryBuilder,
        $records,
        $skip
    ) {
        $this->checkValidQueryBuilder($queryBuilder);

        $queryBuilder->take($records);

        if ($skip) {
            $queryBuilder->skip($skip);
        }
        return;
    }

    /**
     * @param $queryBuilder
     * @return Builder
     */
    private function checkValidQueryBuilder($queryBuilder)
    {
        if (
            !$queryBuilder instanceof Builder &&
            !$queryBuilder instanceof Relation
        ) {
            throw new \InvalidArgumentException('$queryBuilder is expected to be of type ' . Builder::class . ', ' . get_class($queryBuilder) . ' provided.');
        }

        return $queryBuilder;
    }
}
