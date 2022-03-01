<?php

namespace CatLab\Charon\Laravel\Factories;

use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Models\Identifier;
use Exception;

/**
 * Class EntityFactory
 * @package CatLab\Charon\Laravel\Factories
 */
class EntityFactory implements \CatLab\Charon\Interfaces\EntityFactory
{
    /**
     * @param $entityClassName
     * @param Context $context
     * @return mixed
     */
    public function createEntity($entityClassName, Context $context)
    {
        return new $entityClassName;
    }

    /**
     * @param $parent
     * @param $entityClassName
     * @param array $identifiers
     * @param Context $context
     * @return mixed
     * @throws Exception
     */
    public function resolveLinkedEntity($parent, string $entityClassName, Identifier $identifier, Context $context)
    {
        $identifiers = $identifier->getProperties()->transformToEntityValuesMap($context);

        if (isset($identifiers['id'])) {
            return $this->getAuthorizedResolvedEntity(
                $entityClassName::find($identifiers['id'])
            );
        }

        if (count($identifiers) === 0) {
            return null;
        }

        $query = $entityClassName::query();
        foreach ($identifiers as $k => $v) {
            $query->where($k, '=', $v);
        }

        return $this->getAuthorizedResolvedEntity(
            $query->first()
        );
    }

    /**
     * @param string $entityClassName
     * @param Identifier $identifier
     * @param Context $context
     * @return mixed
     * @throws Exception
     */
    public function resolveFromIdentifier(string $entityClassName, Identifier $identifier, Context $context)
    {
        $identifiers = $identifier->getProperties()->transformToEntityValuesMap($context);

        if (isset($identifiers['id'])) {
            return $this->getAuthorizedResolvedEntity(
                $entityClassName::find($identifiers['id'])
            );
        }

        if (count($identifiers) === 0) {
            return null;
        }

        $query = $entityClassName::query();
        foreach ($identifiers as $k => $v) {
            $query->where($k, '=', $v);
        }

        return $this->getAuthorizedResolvedEntity(
            $query->first()
        );
    }

    /**
     * @param $entity
     * @return mixed
     */
    protected function getAuthorizedResolvedEntity($entity)
    {
        // By default, no authorization is done.
        return $entity;
    }
}
