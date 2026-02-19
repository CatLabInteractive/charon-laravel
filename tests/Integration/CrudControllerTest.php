<?php

namespace Tests\Integration;

use Tests\Integration\Models\Pet;
use Tests\Integration\Models\Store;

class CrudControllerTest extends IntegrationTestCase
{
    /**
     * Test index action returns a list of resources.
     */
    public function testIndex()
    {
        $this->seedTestData();

        $response = $this->getJson('/api/stores');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }

    /**
     * Test view action returns a single resource.
     */
    public function testView()
    {
        $data = $this->seedTestData();

        $response = $this->getJson('/api/stores/' . $data['store1']->id);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertEquals('Pet Paradise', $json['name']);
        $this->assertEquals('123 Main St', $json['address']);
    }

    /**
     * Test view action returns 404 for non-existent entity.
     */
    public function testViewNotFound()
    {
        $response = $this->getJson('/api/stores/999');
        $response->assertStatus(404);
    }

    /**
     * Test store action creates a new resource.
     */
    public function testStore()
    {
        $response = $this->postJson('/api/stores', [
            'name' => 'New Store',
            'address' => '789 Elm St'
        ]);

        $response->assertStatus(201);
        $json = $response->json();
        $this->assertEquals('New Store', $json['name']);
        $this->assertEquals('789 Elm St', $json['address']);

        $this->assertDatabaseHas('stores', ['name' => 'New Store', 'address' => '789 Elm St']);
    }

    /**
     * Test store action validates required fields.
     */
    public function testStoreValidation()
    {
        $response = $this->postJson('/api/stores', [
            'address' => 'No Name'
        ]);

        $response->assertStatus(400);
    }

    /**
     * Test edit action updates an existing resource.
     */
    public function testEdit()
    {
        $data = $this->seedTestData();

        $response = $this->putJson('/api/stores/' . $data['store1']->id, [
            'name' => 'Updated Store'
        ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertEquals('Updated Store', $json['name']);

        $this->assertDatabaseHas('stores', ['id' => $data['store1']->id, 'name' => 'Updated Store']);
    }

    /**
     * Test patch action partially updates an existing resource.
     */
    public function testPatch()
    {
        $data = $this->seedTestData();

        $response = $this->patchJson('/api/stores/' . $data['store1']->id, [
            'address' => 'Updated Address'
        ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertEquals('Updated Address', $json['address']);
        // Name should remain unchanged
        $this->assertEquals('Pet Paradise', $json['name']);
    }

    /**
     * Test destroy action deletes a resource.
     */
    public function testDestroy()
    {
        $data = $this->seedTestData();

        $response = $this->deleteJson('/api/stores/' . $data['store2']->id);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);

        $this->assertDatabaseMissing('stores', ['id' => $data['store2']->id]);
    }

    /**
     * Test bulkDestroy action deletes multiple resources.
     */
    public function testBulkDestroy()
    {
        $data = $this->seedTestData();

        $response = $this->deleteJson('/api/stores', [
            'items' => [
                ['id' => $data['store1']->id],
                ['id' => $data['store2']->id]
            ]
        ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertEquals(2, $json['deleted']);

        $this->assertDatabaseMissing('stores', ['id' => $data['store1']->id]);
        $this->assertDatabaseMissing('stores', ['id' => $data['store2']->id]);
    }

    /**
     * Test expand parameter for nested relationships.
     */
    public function testExpandRelationships()
    {
        $this->seedTestData();

        $response = $this->getJson('/api/stores?expand=pets');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('items', $data);

        // Find the store with pets
        $store1 = collect($data['items'])->firstWhere('name', 'Pet Paradise');
        $this->assertNotNull($store1);
        $this->assertArrayHasKey('pets', $store1);
        $this->assertArrayHasKey('items', $store1['pets']);
        $this->assertCount(2, $store1['pets']['items']);
    }

    /**
     * Test expand on single resource view.
     */
    public function testViewWithExpand()
    {
        $data = $this->seedTestData();

        $response = $this->getJson('/api/stores/' . $data['store1']->id . '?expand=pets');

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertArrayHasKey('pets', $json);
        $this->assertArrayHasKey('items', $json['pets']);
        $this->assertCount(2, $json['pets']['items']);
    }

    /**
     * Test deep expand (2 levels: store -> pets -> tags).
     */
    public function testDeepExpand()
    {
        $data = $this->seedTestData();

        $response = $this->getJson('/api/stores/' . $data['store1']->id . '?expand=pets,pets.tags');

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertArrayHasKey('pets', $json);
        $pets = $json['pets']['items'];
        $this->assertGreaterThan(0, count($pets));

        // Find the pet named "Buddy" which has tags
        $buddy = collect($pets)->firstWhere('name', 'Buddy');
        $this->assertNotNull($buddy);
        $this->assertArrayHasKey('tags', $buddy);
        $this->assertArrayHasKey('items', $buddy['tags']);
        $this->assertCount(2, $buddy['tags']['items']);
    }

    /**
     * Test 3-level deep expand (store -> pets -> tags -> metadata).
     */
    public function testThreeLevelDeepExpand()
    {
        $data = $this->seedTestData();

        $response = $this->getJson(
            '/api/stores/' . $data['store1']->id . '?expand=pets,pets.tags,pets.tags.metadata'
        );

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertArrayHasKey('pets', $json);
        $pets = $json['pets']['items'];

        $buddy = collect($pets)->firstWhere('name', 'Buddy');
        $this->assertNotNull($buddy);

        $tags = $buddy['tags']['items'];
        $friendlyTag = collect($tags)->firstWhere('name', 'friendly');
        $this->assertNotNull($friendlyTag);
        $this->assertArrayHasKey('metadata', $friendlyTag);
        $this->assertArrayHasKey('items', $friendlyTag['metadata']);
        $this->assertCount(2, $friendlyTag['metadata']['items']);
    }

    /**
     * Test index on standalone pet resource.
     */
    public function testPetIndex()
    {
        $this->seedTestData();

        $response = $this->getJson('/api/pets');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(3, $data['items']);
    }

    /**
     * Test view a pet with expanded store relationship.
     */
    public function testPetViewWithExpandedStore()
    {
        $data = $this->seedTestData();

        $response = $this->getJson('/api/pets/' . $data['pet1']->id . '?expand=store');

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertEquals('Buddy', $json['name']);
        $this->assertArrayHasKey('store', $json);
        $this->assertEquals('Pet Paradise', $json['store']['name']);
    }

    /**
     * Test creating a pet with expanded tags (writeable relationship).
     */
    public function testPetStoreWithTags()
    {
        $data = $this->seedTestData();

        $response = $this->postJson('/api/pets', [
            'name' => 'Rocky',
            'status' => 'available',
            'tags' => [
                'items' => [
                    ['name' => 'active'],
                    ['name' => 'outdoor']
                ]
            ]
        ]);

        $response->assertStatus(201);
        $json = $response->json();
        $this->assertEquals('Rocky', $json['name']);

        // Verify tags were created in DB
        $pet = Pet::where('name', 'Rocky')->first();
        $this->assertNotNull($pet);
        $this->assertEquals(2, $pet->tags()->count());
    }
}
