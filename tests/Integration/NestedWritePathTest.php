<?php

namespace Tests\Integration;

use Tests\Integration\Models\Pet;
use Tests\Integration\Models\Store;
use Tests\Integration\Models\Tag;
use Tests\Integration\Models\TagMetadata;

/**
 * Covers the nested write path exercised by RelationshipValue::toEntity() /
 * childResourceToEntity(): adding brand new children, editing existing children
 * by identifier, removing children that are omitted from the payload
 * ("remove all except"), and resolving linkable relationships by identifier.
 */
class NestedWritePathTest extends IntegrationTestCase
{
    /**
     * (a) Payload adds new children: a writeable-many relationship (Tag.metadata)
     * with brand new items (no identifier) should create rows that are correctly
     * associated with the parent, and nothing else should be touched.
     */
    public function testStoreWithNewChildrenCreatesAndAssociatesThem()
    {
        $data = $this->seedTestData();

        $response = $this->postJson('/api/pets/' . $data['pet1']->id . '/tags', [
            'name' => 'smart',
            'metadata' => [
                'items' => [
                    ['key' => 'iq', 'value' => 'high'],
                    ['key' => 'trained', 'value' => 'yes']
                ]
            ]
        ]);

        $response->assertStatus(201);
        $json = $response->json();
        $this->assertEquals('smart', $json['name']);

        $tag = Tag::where('name', 'smart')->first();
        $this->assertNotNull($tag);

        // Exactly the two new metadata rows exist, and they belong to the new tag.
        $this->assertEquals(2, $tag->metadata()->count());

        $iq = TagMetadata::where('tag_id', $tag->id)->where('key', 'iq')->first();
        $this->assertNotNull($iq);
        $this->assertEquals('high', $iq->value);

        $trained = TagMetadata::where('tag_id', $tag->id)->where('key', 'trained')->first();
        $this->assertNotNull($trained);
        $this->assertEquals('yes', $trained->value);

        // The metadata belonging to other tags was left untouched.
        $this->assertEquals(2, $data['tag1']->metadata()->count());
        $this->assertEquals(1, $data['tag2']->metadata()->count());
    }

    /**
     * (b) Edits existing children by identifier: a PUT with a nested collection
     * containing an existing child's id + a changed field value must update the
     * existing row in place (same row id), not delete + recreate it.
     */
    public function testEditExistingChildByIdentifierUpdatesInPlace()
    {
        $data = $this->seedTestData();

        /** @var TagMetadata $existing */
        $existing = TagMetadata::where('tag_id', $data['tag1']->id)->where('key', 'level')->first();
        $this->assertNotNull($existing);
        $existingId = $existing->id;

        // tag1 currently has two metadata rows: 'level' => 'high' and 'since' => '2023'.
        $response = $this->putJson('/api/pets/' . $data['pet1']->id . '/tags/' . $data['tag1']->id, [
            'name' => $data['tag1']->name,
            'metadata' => [
                'items' => [
                    ['id' => $existingId, 'key' => 'level', 'value' => 'extreme'],
                    ['id' => $data['tag1']->metadata()->where('key', 'since')->first()->id, 'key' => 'since', 'value' => '2023'],
                ]
            ]
        ]);

        $response->assertStatus(200);

        // Same row id, updated value — not deleted and recreated.
        $this->assertDatabaseHas('tag_metadata', [
            'id' => $existingId,
            'key' => 'level',
            'value' => 'extreme',
        ]);

        $this->assertEquals(2, $data['tag1']->metadata()->count());
    }

    /**
     * (c) Omitted children are removed (remove-all-except): a PUT with a nested
     * collection that omits a child which currently exists must result in that
     * child no longer being associated with the parent. For a HasMany relation,
     * this implementation actually hard-deletes the omitted child (see
     * PropertySetter::removeAllChildrenExcept() -> Model::saveTheChildren(),
     * which calls $child->delete() for HasMany relations).
     */
    public function testOmittedChildIsRemoved()
    {
        $data = $this->seedTestData();

        $keptId = TagMetadata::where('tag_id', $data['tag1']->id)->where('key', 'since')->first()->id;
        $omittedId = TagMetadata::where('tag_id', $data['tag1']->id)->where('key', 'level')->first()->id;

        $response = $this->putJson('/api/pets/' . $data['pet1']->id . '/tags/' . $data['tag1']->id, [
            'name' => $data['tag1']->name,
            'metadata' => [
                'items' => [
                    ['id' => $keptId, 'key' => 'since', 'value' => '2023'],
                ]
            ]
        ]);

        $response->assertStatus(200);

        // The kept child is still there.
        $this->assertDatabaseHas('tag_metadata', ['id' => $keptId]);

        // The omitted child is gone entirely (hard deleted), not just detached.
        $this->assertDatabaseMissing('tag_metadata', ['id' => $omittedId]);

        $this->assertEquals(1, $data['tag1']->metadata()->count());
    }

    /**
     * (d1) Linkable relationship resolves an existing identifier: PetDefinition's
     * `store` relationship is declared ->linkable() (link only, no ->writeable(),
     * so canCreateNewChildren() is false and only identifier-based attach is
     * possible). Sending only the identifier of a *different*, already existing
     * store must re-associate the pet with that store without modifying any of
     * the target store's own fields.
     */
    public function testLinkableRelationshipAttachesExistingEntityByIdentifier()
    {
        $data = $this->seedTestData();

        $this->assertEquals($data['store1']->id, $data['pet1']->store_id);

        $response = $this->putJson('/api/pets/' . $data['pet1']->id, [
            'name' => $data['pet1']->name,
            'store' => [
                'id' => $data['store2']->id,
            ],
        ]);

        $response->assertStatus(200);

        // The pet is now attached to store2.
        $this->assertDatabaseHas('pets', [
            'id' => $data['pet1']->id,
            'store_id' => $data['store2']->id,
        ]);

        // store2's own fields were not touched by the link operation.
        $this->assertDatabaseHas('stores', [
            'id' => $data['store2']->id,
            'name' => 'Animal Kingdom',
            'address' => '456 Oak Ave',
        ]);

        // store1 (the old parent) still exists untouched.
        $this->assertDatabaseHas('stores', [
            'id' => $data['store1']->id,
            'name' => 'Pet Paradise',
            'address' => '123 Main St',
        ]);
    }

    /**
     * (d2) Linkable relationship rejects an unknown identifier: resolving a
     * non-existent store id must not silently succeed. childResourceToEntity()
     * cannot resolve the entity and the field cannot create new children
     * (->linkable() without ->writeable() means canCreateNewChildren() is
     * false), so it throws EntityNotFoundException.
     *
     * This test harness (a bare orchestra/testbench app) does not register
     * CharonErrorHandler with Laravel's exception handler -- that class exists
     * in src/Exceptions/CharonErrorHandler.php purely as a utility for
     * consuming applications to wire into their own exception handler, and
     * maps EntityNotFoundException to a 404 JSON:API response when it is used.
     * Left unregistered (as here), the exception is uncaught anywhere in the
     * request lifecycle (CrudController::edit() only catches
     * ResourceValidationException) and bubbles up to Laravel's default
     * handler, which renders it as a 500. We assert the actual, currently
     * observed behavior: a non-2xx (specifically 500) response, and that the
     * pet's store association is left unchanged.
     */
    public function testLinkableRelationshipRejectsUnknownIdentifier()
    {
        $data = $this->seedTestData();

        $unknownId = Store::max('id') + 1000;

        $response = $this->putJson('/api/pets/' . $data['pet1']->id, [
            'name' => $data['pet1']->name,
            'store' => [
                'id' => $unknownId,
            ],
        ]);

        $response->assertStatus(500);

        // The pet's original store association is unaffected.
        $this->assertDatabaseHas('pets', [
            'id' => $data['pet1']->id,
            'store_id' => $data['store1']->id,
        ]);
    }
}
