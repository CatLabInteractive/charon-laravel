<?php

namespace CatLab\Charon\Laravel\Resolvers;

use CatLab\Base\Enum\Operator;
use CatLab\Charon\Collections\PropertyValueCollection;
use CatLab\Charon\Exceptions\InvalidPropertyException;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Interfaces\ResourceTransformer;
use CatLab\Charon\Models\Properties\Base\Field;
use CatLab\Charon\Models\Properties\RelationshipField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

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
     * @param PropertyValueCollection $identifiers
     * @param Context $context
     * @return mixed
     * @throws InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    public function getChildByIdentifiers(
        ResourceTransformer $transformer,
        RelationshipField $field,
        $parentEntity,
        PropertyValueCollection $identifiers,
        Context $context
    ) {
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
            if ($this->entityEquals($transformer, $entity, $identifiers, $context)) {
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
        if (!$queryBuilder instanceof Builder) {
            throw new \InvalidArgumentException('$queryBuilder is expected to be of type ' . Builder::class . ', ' . get_class($queryBuilder) . ' provided.');
        }

        $queryBuilder->where($this->getQualifiedName($field), $operator, $value);
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
        if (!$queryBuilder instanceof Builder) {
            throw new \InvalidArgumentException('$queryBuilder is expected to be of type ' . Builder::class . ', ' . get_class($queryBuilder) . ' provided.');
        }

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
        if (!$queryBuilder instanceof Builder) {
            throw new \InvalidArgumentException('$queryBuilder is expected to be of type ' . Builder::class . ', ' . get_class($queryBuilder) . ' provided.');
        }

        return $queryBuilder->count();
    }
}
