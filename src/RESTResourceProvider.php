<?php

namespace CatLab\Charon\Laravel;

use CatLab\Charon\Factories\ResourceFactory;
use CatLab\Charon\Laravel\Resolvers\PropertyResolver;
use CatLab\Charon\Laravel\Resolvers\PropertySetter;
use CatLab\Charon\Laravel\Resolvers\QueryAdapter;
use CatLab\Charon\Laravel\ResourceTransformer;
use CatLab\Charon\Resolvers\RequestResolver;
use Illuminate\Support\ServiceProvider;

/**
 * Class RESTResourceProvider
 * @package CatLab\RESTResource\Laravel
 */
class RESTResourceProvider extends ServiceProvider
{
    /**
     * Register the Resource Transformer singleton
     *
     * @return void
     */
    public function register()
    {
        $this->registerResourceTransformer();
    }

    /**
     *
     */
    protected function registerResourceTransformer()
    {
        $parent = $this;

        // Our own custom gatekeeper
        $this->app->singleton(\CatLab\Charon\Interfaces\ResourceTransformer::class, function() use ($parent) {
            return $parent->createResourceTransformer();
        });
    }

    /**
     * @return ResourceTransformer
     */
    protected function createResourceTransformer()
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
