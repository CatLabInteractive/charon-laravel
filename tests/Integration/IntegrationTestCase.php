<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Tests\Integration\Controllers\PetController;
use Tests\Integration\Controllers\PetTagController;
use Tests\Integration\Controllers\StoreController;
use Tests\Integration\Controllers\StorePetController;

abstract class IntegrationTestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => env('DB_DRIVER', 'sqlite'),
            'database' => env('DB_DATABASE', ':memory:'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'username' => env('DB_USERNAME', ''),
            'password' => env('DB_PASSWORD', ''),
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->dropTables();

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->nullable();
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('pet_id')->constrained('pets')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('tag_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('value')->nullable();
            $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
            $table->timestamps();
        });

        $this->beforeApplicationDestroyed(function () {
            $this->dropTables();
        });
    }

    private function dropTables()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('tag_metadata');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('pets');
        Schema::dropIfExists('stores');
        Schema::enableForeignKeyConstraints();
    }

    protected function defineRoutes($router)
    {
        // CRUD routes for stores
        $router->get('/api/stores', [StoreController::class, 'index']);
        $router->post('/api/stores', [StoreController::class, 'store']);
        $router->get('/api/stores/{id}', [StoreController::class, 'view']);
        $router->put('/api/stores/{id}', [StoreController::class, 'edit']);
        $router->patch('/api/stores/{id}', [StoreController::class, 'patch']);
        $router->delete('/api/stores/{id}', [StoreController::class, 'destroy']);
        $router->delete('/api/stores', [StoreController::class, 'bulkDestroy']);

        // CRUD routes for pets (standalone)
        $router->get('/api/pets', [PetController::class, 'index']);
        $router->post('/api/pets', [PetController::class, 'store']);
        $router->get('/api/pets/{id}', [PetController::class, 'view']);
        $router->put('/api/pets/{id}', [PetController::class, 'edit']);
        $router->patch('/api/pets/{id}', [PetController::class, 'patch']);
        $router->delete('/api/pets/{id}', [PetController::class, 'destroy']);

        // Child CRUD routes for pets under stores
        $router->get('/api/stores/{store}/pets', [StorePetController::class, 'index']);
        $router->post('/api/stores/{store}/pets', [StorePetController::class, 'store']);
        $router->get('/api/stores/{store}/pets/{id}', [StorePetController::class, 'view']);
        $router->put('/api/stores/{store}/pets/{id}', [StorePetController::class, 'edit']);
        $router->patch('/api/stores/{store}/pets/{id}', [StorePetController::class, 'patch']);
        $router->delete('/api/stores/{store}/pets/{id}', [StorePetController::class, 'destroy']);
        $router->delete('/api/stores/{store}/pets', [StorePetController::class, 'bulkDestroy']);

        // Child CRUD routes for tags under pets
        $router->get('/api/pets/{pet}/tags', [PetTagController::class, 'index']);
        $router->post('/api/pets/{pet}/tags', [PetTagController::class, 'store']);
        $router->get('/api/pets/{pet}/tags/{id}', [PetTagController::class, 'view']);
        $router->put('/api/pets/{pet}/tags/{id}', [PetTagController::class, 'edit']);
        $router->patch('/api/pets/{pet}/tags/{id}', [PetTagController::class, 'patch']);
        $router->delete('/api/pets/{pet}/tags/{id}', [PetTagController::class, 'destroy']);
        $router->delete('/api/pets/{pet}/tags', [PetTagController::class, 'bulkDestroy']);
    }

    protected function seedTestData()
    {
        $store1 = \Tests\Integration\Models\Store::create(['name' => 'Pet Paradise', 'address' => '123 Main St']);
        $store2 = \Tests\Integration\Models\Store::create(['name' => 'Animal Kingdom', 'address' => '456 Oak Ave']);

        $pet1 = \Tests\Integration\Models\Pet::create(['name' => 'Buddy', 'status' => 'available', 'store_id' => $store1->id]);
        $pet2 = \Tests\Integration\Models\Pet::create(['name' => 'Max', 'status' => 'sold', 'store_id' => $store1->id]);
        $pet3 = \Tests\Integration\Models\Pet::create(['name' => 'Luna', 'status' => 'available', 'store_id' => $store2->id]);

        $tag1 = \Tests\Integration\Models\Tag::create(['name' => 'friendly', 'pet_id' => $pet1->id]);
        $tag2 = \Tests\Integration\Models\Tag::create(['name' => 'trained', 'pet_id' => $pet1->id]);
        $tag3 = \Tests\Integration\Models\Tag::create(['name' => 'playful', 'pet_id' => $pet2->id]);

        \Tests\Integration\Models\TagMetadata::create(['key' => 'level', 'value' => 'high', 'tag_id' => $tag1->id]);
        \Tests\Integration\Models\TagMetadata::create(['key' => 'since', 'value' => '2023', 'tag_id' => $tag1->id]);
        \Tests\Integration\Models\TagMetadata::create(['key' => 'certified', 'value' => 'yes', 'tag_id' => $tag2->id]);

        return compact('store1', 'store2', 'pet1', 'pet2', 'pet3', 'tag1', 'tag2', 'tag3');
    }
}
