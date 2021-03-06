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
    public function resolveLinkedEntity($parent, string $entityClassName, array $identifiers, Context $context)
    {
        if (isset($identifiers['id'])) {
            return $entityClassName::find($identifiers['id']);
        }

        $query = $entityClassName::query();
        foreach ($identifiers as $k => $v) {
            $query->where($k, '=', $v);
        }

        return $query->first();
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
        $data = $identifier->toArray();
        if (isset($data['id'])) {
            return $entityClassName::find($data['id']);
        }

        $query = $entityClassName::query();
        foreach ($data as $k => $v) {
            $query->where($k, '=', $v);
        }

        return $query->first();
    }
}
