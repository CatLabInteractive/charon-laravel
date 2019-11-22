<?php

namespace CatLab\Charon\ResourceTransformers\Tests;

use CatLab\Charon\Laravel\ResourceTransformer;

/**
 * Class BaseTest
 * @package CatLab\Charon\ResourceTransformers\Tests
 */
abstract class BaseTest extends \PHPUnit_Framework_TestCase
{
    public function getResourceTransformer()
    {
        return new ResourceTransformer(
            new \CatLab\Charon\Resolvers\PropertyResolver()
        );
    }
}
