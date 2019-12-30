<?php

namespace Tests;

use CatLab\Charon\Factories\ResourceFactory;
use CatLab\Charon\Laravel\Resolvers\PropertyResolver;
use CatLab\Charon\Laravel\Resolvers\PropertySetter;
use CatLab\Charon\Laravel\Resolvers\QueryAdapter;
use CatLab\Charon\Laravel\ResourceTransformer;
use CatLab\Charon\Resolvers\RequestResolver;

/**
 * Class BaseTest
 * @package CatLab\Charon\ResourceTransformers\Tests
 */
abstract class BaseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return ResourceTransformer
     */
    public function getResourceTransformer()
    {
        return new ResourceTransformer(
            new PropertyResolver(),
            new PropertySetter(),
            new RequestResolver(),
            new QueryAdapter(),
            new ResourceFactory()
        );
    }
}
