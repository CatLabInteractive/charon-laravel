<?php

namespace Tests\Integration;

use Tests\Integration\Models\Pet;

/**
 * Covers client references ('$ref') applied at write time: CrudController's
 * store() drains ClientReferenceMap::getPendingLinks() after every entity in
 * a bulk payload has been saved, applying each pending link through the same
 * PropertySetter path an immediately-resolved link uses (setChild()/
 * addChildren()), and wraps every entity save + the drain in a single
 * transaction so an unresolved '$ref' rolls back the whole batch.
 *
 * Uses PetDefinition's self-referencing 'linkedPet' relationship
 * (->one()->linkable(), no ->writeable() -- link only, mirrors eukles'
 * WorkflowStep 'linkWithRole:next' shape) to let one pet in a bulk POST link
 * to a sibling pet purely by '$ref', with no identifier of its own.
 */
class ClientReferenceWriteTest extends IntegrationTestCase
{
    /**
     * B is declared before A in the payload, so by the time A's 'linkedPet'
     * field is walked, B has already been created *and* registered under its
     * '$ref' (registration happens right after each item's own save) --
     * charon resolves the link immediately and no pending link is ever
     * recorded for it.
     */
    public function testSiblingsCreatedInOnePayloadCanLinkByRef()
    {
        $response = $this->postJson('/api/pets', [
            'items' => [
                [ '$ref' => 'tmp-b', 'name' => 'Buddy' ],
                [ 'name' => 'Rex', 'linkedPet' => [ '$ref' => 'tmp-b' ] ],
            ],
        ]);

        $response->assertStatus(201);

        $buddy = Pet::where('name', 'Buddy')->first();
        $rex = Pet::where('name', 'Rex')->first();

        $this->assertNotNull($buddy);
        $this->assertNotNull($rex);
        $this->assertEquals($buddy->id, $rex->linked_pet_id);
    }

    /**
     * A links tmp-b BEFORE b is declared anywhere in the payload: charon
     * can't resolve the ref while walking A (it records a pending link
     * instead of throwing), so the link only becomes real once the whole
     * payload has been processed and the drain runs. The drain must also
     * persist it: A was already saved once, without the link, earlier in the
     * same batch.
     */
    public function testForwardReferenceResolvesInDeferredPass()
    {
        $response = $this->postJson('/api/pets', [
            'items' => [
                [ 'name' => 'Rex', 'linkedPet' => [ '$ref' => 'tmp-b' ] ],
                [ '$ref' => 'tmp-b', 'name' => 'Buddy' ],
            ],
        ]);

        $response->assertStatus(201);

        $buddy = Pet::where('name', 'Buddy')->first();
        $rex = Pet::where('name', 'Rex')->first();

        $this->assertNotNull($buddy);
        $this->assertNotNull($rex);
        $this->assertEquals($buddy->id, $rex->linked_pet_id);
    }

    /**
     * The response's meta.$refs maps every '$ref' used in the payload to the
     * real primary key of the entity it ended up registered against --
     * regardless of whether the ref resolved immediately or via the deferred
     * drain.
     */
    public function testResponseContainsRefToIdMapping()
    {
        $response = $this->postJson('/api/pets', [
            'items' => [
                [ 'name' => 'Rex', '$ref' => 'tmp-a', 'linkedPet' => [ '$ref' => 'tmp-b' ] ],
                [ '$ref' => 'tmp-b', 'name' => 'Buddy' ],
            ],
        ]);

        $response->assertStatus(201);
        $json = $response->json();

        $rex = Pet::where('name', 'Rex')->first();
        $buddy = Pet::where('name', 'Buddy')->first();

        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('$refs', $json['meta']);
        $this->assertEquals([
            'tmp-a' => $rex->id,
            'tmp-b' => $buddy->id,
        ], $json['meta']['$refs']);
    }

    /**
     * A link to a '$ref' that no resource in the payload ever registers is a
     * 422 naming the ref, and the whole transaction (the entity's save + the
     * failed drain) rolls back -- nothing from this request is left in the
     * database.
     */
    public function testUnknownRefYields422()
    {
        $response = $this->postJson('/api/pets', [
            'name' => 'Rex',
            'linkedPet' => [ '$ref' => 'tmp-nope' ],
        ]);

        $response->assertStatus(422);

        $json = $response->json();
        $this->assertStringContainsString('tmp-nope', json_encode($json));

        $this->assertEquals(0, Pet::count());
    }

    /**
     * Same forward-reference shape as testForwardReferenceResolvesInDeferredPass,
     * but through PetDefinition's "many" self-referencing 'relatedPets'
     * relationship (a linkable, non-writeable BelongsToMany) instead of the
     * "one" 'linkedPet' -- exercises the Cardinality::MANY branch of
     * CrudController::drainClientReferences() (PropertySetter::addChildren(),
     * not setChild()), and the fact that applying it must actually persist
     * the BelongsToMany pivot row: addChildren() on a Laravel Model only
     * buffers the child in memory (Model::addChildrenToEntity()) until
     * saveRecursively() -> saveTheChildren() runs.
     */
    public function testForwardReferenceResolvesInDeferredPassForManyRelationship()
    {
        $response = $this->postJson('/api/pets', [
            'items' => [
                [ 'name' => 'Rex', 'relatedPets' => [ 'items' => [ [ '$ref' => 'tmp-b' ] ] ] ],
                [ '$ref' => 'tmp-b', 'name' => 'Buddy' ],
            ],
        ]);

        $response->assertStatus(201);

        $buddy = Pet::where('name', 'Buddy')->first();
        $rex = Pet::where('name', 'Rex')->first();

        $this->assertNotNull($buddy);
        $this->assertNotNull($rex);

        // Re-fetch: relatedPets is a relation, not an attribute -- assert against
        // the persisted pivot row, not anything still held on the in-memory $rex.
        $persistedRelatedPetIds = Pet::find($rex->id)->relatedPets()->pluck('pets.id')->all();
        $this->assertEquals([ $buddy->id ], $persistedRelatedPetIds);

        $this->assertDatabaseHas('related_pets', [
            'pet_id' => $rex->id,
            'related_pet_id' => $buddy->id,
        ]);
    }
}
