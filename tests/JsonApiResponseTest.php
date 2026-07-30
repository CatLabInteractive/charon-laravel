<?php

namespace Tests;

use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\JsonApi\Models\JsonApiResponse;
use CatLab\Charon\Models\Context;
use Tests\Petstore\Definitions\PetDefinition;
use Tests\Petstore\Models\Pet;

/**
 * Class JsonApiResponseTest
 *
 * Covers ResourceResponse::setMeta() ("$refs", see CrudController::
 * getClientReferenceMapping()) actually reaching the encoded body through
 * JsonApiResponse, which overrides toArray() entirely (via its own
 * toJsonApi()/addResources()) instead of calling parent::toArray() -- so the
 * generic merge in ResourceResponse::toArray() never runs for a JSON:API
 * response; JsonApiResponse::addResources() has to merge it itself.
 *
 * @package CatLab\Charon\Laravel\Tests
 */
class JsonApiResponseTest extends BaseTest
{
    private function makePet(int $id, string $name): Pet
    {
        return (new Pet())->setId($id)->setName($name)->setStatus(Pet::STATUS_AVAILABLE);
    }

    private function getContext(): Context
    {
        $context = new Context(Action::VIEW);
        // Keep the encoded body minimal: identifier + name only, no
        // relationships -- irrelevant to what this test covers.
        $context->showFields([ 'pet-id', 'name' ]);
        return $context;
    }

    /**
     * A single (non-collection) resource has no meta mechanism of its own
     * (unlike ResourceCollection) -- previously JsonApiResponse didn't emit
     * any "meta" key at all for this case (the line was commented out).
     * setMeta() must be the only source of "meta" here, and must actually
     * show up in the encoded output.
     */
    public function testMetaIsEmittedForSingleResource(): void
    {
        $transformer = $this->getResourceTransformer();
        $context = $this->getContext();

        $resource = $transformer->toResource(PetDefinition::class, $this->makePet(1, 'Buddy'), $context);

        $response = new JsonApiResponse($resource, $context);
        $response->setMeta([ '$refs' => [ 'tmp-1' => 1 ] ]);

        $data = $response->toArray();

        $this->assertArrayHasKey('meta', $data);
        $this->assertEquals([ '$refs' => [ 'tmp-1' => 1 ] ], $data['meta']);
    }

    /**
     * With no setMeta() call, a single resource must not gain a stray empty
     * "meta" key -- toJsonApi() strips it when empty, same as before this
     * change for the collection branch.
     */
    public function testNoMetaKeyForSingleResourceWithoutSetMeta(): void
    {
        $transformer = $this->getResourceTransformer();
        $context = $this->getContext();

        $resource = $transformer->toResource(PetDefinition::class, $this->makePet(1, 'Buddy'), $context);

        $response = new JsonApiResponse($resource, $context);
        $data = $response->toArray();

        $this->assertArrayNotHasKey('meta', $data);
    }

    /**
     * A collection's own meta (e.g. the pre-existing "bulk" flag,
     * ResourceCollection::addMeta()) and the response-level meta from
     * setMeta() must both show up, merged into one "meta" object -- this is
     * what silently dropped setMeta()'s contents before this fix (the
     * collection branch used $resource->getMeta() only).
     */
    public function testMetaMergesWithCollectionMeta(): void
    {
        $transformer = $this->getResourceTransformer();
        $context = $this->getContext();

        $resources = $transformer->toResources(PetDefinition::class, [
            $this->makePet(1, 'Buddy'),
            $this->makePet(2, 'Rex'),
        ], $context);
        $resources->addMeta('bulk', true);

        $response = new JsonApiResponse($resources, $context);
        $response->setMeta([ '$refs' => [ 'tmp-1' => 1 ] ]);

        $data = $response->toArray();

        $this->assertArrayHasKey('meta', $data);
        $this->assertEquals([
            'bulk' => true,
            '$refs' => [ 'tmp-1' => 1 ],
        ], $data['meta']);
    }

    /**
     * On a key conflict between the response-level meta and the resource
     * collection's own meta, the collection's own meta wins -- documented
     * precedence in JsonApiResponse::addResources().
     */
    public function testCollectionMetaWinsOnKeyConflict(): void
    {
        $transformer = $this->getResourceTransformer();
        $context = $this->getContext();

        $resources = $transformer->toResources(PetDefinition::class, [
            $this->makePet(1, 'Buddy'),
        ], $context);
        $resources->addMeta('$refs', [ 'from-collection' => true ]);

        $response = new JsonApiResponse($resources, $context);
        $response->setMeta([ '$refs' => [ 'from-response' => true ] ]);

        $data = $response->toArray();

        $this->assertEquals([ '$refs' => [ 'from-collection' => true ] ], $data['meta']);
    }
}
