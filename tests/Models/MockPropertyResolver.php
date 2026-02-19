<?php

namespace Tests\Models;

/**
 * Class MockPropertyResolver
 * @package Tests\Models
 */
class MockPropertyResolver extends \CatLab\Charon\Resolvers\PropertyResolver
{
    /**
     * @param string $path
     * @return mixed
     */
    public function splitPathParameters(string $path): array
    {
        return parent::splitPathParameters($path);
    }
}
