<?php

namespace Tests\Integration;

use Tests\Integration\Models\Pet;
use Tests\Integration\Models\Tag;
use Tests\Integration\Models\TagMetadata;

class ChildCrudControllerTest extends IntegrationTestCase
{
    /**
     * Test index of child resources under a parent.
     */
    public function testChildIndex()
    {
        $data = $this->seedTestData();

        $response = $this->getJson('/api/stores/' . $data['store1']->id . '/pets');

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertArrayHasKey('items', $json);
        $this->assertCount(2, $json['items']);
    }

    /**
     * Test index returns only children of the specified parent.
     */
    public function testChildIndexScopedToParent()
    {
        $data = $this->seedTestData();

        $response = $this->getJson('/api/stores/' . $data['store2']->id . '/pets');

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertArrayHasKey('items', $json);
        $this->assertCount(1, $json['items']);
        $this->assertEquals('Luna', $json['items'][0]['name']);
    }

    /**
     * Test store action creates a child resource associated with parent.
     */
    public function testChildStore()
    {
        $data = $this->seedTestData();

        $response = $this->postJson('/api/stores/' . $data['store1']->id . '/pets', [
            'name' => 'Charlie',
            'status' => 'available'
        ]);

        $response->assertStatus(201);
        $json = $response->json();
        $this->assertEquals('Charlie', $json['name']);

        // Verify the pet is attached to the correct store
        $pet = Pet::where('name', 'Charlie')->first();
        $this->assertNotNull($pet);
        $this->assertEquals($data['store1']->id, $pet->store_id);
    }

    /**
     * Test view action for a child resource.
     */
    public function testChildView()
    {
        $data = $this->seedTestData();

        $response = $this->getJson('/api/stores/' . $data['store1']->id . '/pets/' . $data['pet1']->id);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertEquals('Buddy', $json['name']);
    }

    /**
     * Test edit action on a child resource.
     */
    public function testChildEdit()
    {
        $data = $this->seedTestData();

        $response = $this->putJson(
            '/api/stores/' . $data['store1']->id . '/pets/' . $data['pet1']->id,
            ['name' => 'Buddy Updated']
        );

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertEquals('Buddy Updated', $json['name']);

        $this->assertDatabaseHas('pets', ['id' => $data['pet1']->id, 'name' => 'Buddy Updated']);
    }

    /**
     * Test patch action on a child resource.
     */
    public function testChildPatch()
    {
        $data = $this->seedTestData();

        $response = $this->patchJson(
            '/api/stores/' . $data['store1']->id . '/pets/' . $data['pet1']->id,
            ['status' => 'sold']
        );

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertEquals('sold', $json['status']);
        $this->assertEquals('Buddy', $json['name']);
    }

    /**
     * Test destroy action on a child resource.
     */
    public function testChildDestroy()
    {
        $data = $this->seedTestData();

        $response = $this->deleteJson(
            '/api/stores/' . $data['store1']->id . '/pets/' . $data['pet1']->id
        );

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);

        $this->assertDatabaseMissing('pets', ['id' => $data['pet1']->id]);
    }

    /**
     * Test bulk destroy child resources.
     */
    public function testChildBulkDestroy()
    {
        $data = $this->seedTestData();

        $response = $this->deleteJson(
            '/api/stores/' . $data['store1']->id . '/pets',
            [
                'items' => [
                    ['id' => $data['pet1']->id],
                    ['id' => $data['pet2']->id]
                ]
            ]
        );

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertEquals(2, $json['deleted']);

        $this->assertDatabaseMissing('pets', ['id' => $data['pet1']->id]);
        $this->assertDatabaseMissing('pets', ['id' => $data['pet2']->id]);
    }

    /**
     * Test child index with expand parameter.
     */
    public function testChildIndexWithExpand()
    {
        $data = $this->seedTestData();

        $response = $this->getJson('/api/stores/' . $data['store1']->id . '/pets?expand=tags');

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertArrayHasKey('items', $json);

        $buddy = collect($json['items'])->firstWhere('name', 'Buddy');
        $this->assertNotNull($buddy);
        $this->assertArrayHasKey('tags', $buddy);
        $this->assertArrayHasKey('items', $buddy['tags']);
        $this->assertCount(2, $buddy['tags']['items']);
    }

    /**
     * Test deep child nesting (tags under pets).
     */
    public function testTagIndex()
    {
        $data = $this->seedTestData();

        $response = $this->getJson('/api/pets/' . $data['pet1']->id . '/tags');

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertArrayHasKey('items', $json);
        $this->assertCount(2, $json['items']);
    }

    /**
     * Test creating tag as child of pet.
     */
    public function testTagStore()
    {
        $data = $this->seedTestData();

        $response = $this->postJson('/api/pets/' . $data['pet1']->id . '/tags', [
            'name' => 'loyal'
        ]);

        $response->assertStatus(201);
        $json = $response->json();
        $this->assertEquals('loyal', $json['name']);

        $tag = Tag::where('name', 'loyal')->first();
        $this->assertNotNull($tag);
        $this->assertEquals($data['pet1']->id, $tag->pet_id);
    }

    /**
     * Test viewing a tag.
     */
    public function testTagView()
    {
        $data = $this->seedTestData();

        $response = $this->getJson('/api/pets/' . $data['pet1']->id . '/tags/' . $data['tag1']->id);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertEquals('friendly', $json['name']);
    }

    /**
     * Test edit a tag.
     */
    public function testTagEdit()
    {
        $data = $this->seedTestData();

        $response = $this->putJson(
            '/api/pets/' . $data['pet1']->id . '/tags/' . $data['tag1']->id,
            ['name' => 'super friendly']
        );

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertEquals('super friendly', $json['name']);

        $this->assertDatabaseHas('tags', ['id' => $data['tag1']->id, 'name' => 'super friendly']);
    }

    /**
     * Test deleting a tag.
     */
    public function testTagDestroy()
    {
        $data = $this->seedTestData();

        $response = $this->deleteJson(
            '/api/pets/' . $data['pet1']->id . '/tags/' . $data['tag1']->id
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tags', ['id' => $data['tag1']->id]);
    }

    /**
     * Test tag expand with deep metadata relationship.
     */
    public function testTagWithExpandedMetadata()
    {
        $data = $this->seedTestData();

        $response = $this->getJson(
            '/api/pets/' . $data['pet1']->id . '/tags/' . $data['tag1']->id . '?expand=metadata'
        );

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertEquals('friendly', $json['name']);
        $this->assertArrayHasKey('metadata', $json);
        $this->assertArrayHasKey('items', $json['metadata']);
        $this->assertCount(2, $json['metadata']['items']);
    }

    /**
     * Test creating a tag with nested metadata (writeable child).
     */
    public function testTagStoreWithMetadata()
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
        $this->assertEquals(2, $tag->metadata()->count());
    }

    /**
     * Test bulk destroy tags scoped to parent pet.
     */
    public function testTagBulkDestroy()
    {
        $data = $this->seedTestData();

        $response = $this->deleteJson('/api/pets/' . $data['pet1']->id . '/tags', [
            'items' => [
                ['id' => $data['tag1']->id],
                ['id' => $data['tag2']->id]
            ]
        ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertEquals(2, $json['deleted']);
    }

    /**
     * Test that bulk destroy on child only deletes children belonging to the parent.
     */
    public function testChildBulkDestroyRespectsParentScope()
    {
        $data = $this->seedTestData();

        // Try to bulk delete pet3 (belongs to store2) through store1's endpoint
        $response = $this->deleteJson(
            '/api/stores/' . $data['store1']->id . '/pets',
            [
                'items' => [
                    ['id' => $data['pet3']->id]
                ]
            ]
        );

        $response->assertStatus(200);
        $json = $response->json();
        // Should not delete pet3 since it belongs to store2
        $this->assertEquals(0, $json['deleted']);

        // pet3 should still exist
        $this->assertDatabaseHas('pets', ['id' => $data['pet3']->id]);
    }
}
