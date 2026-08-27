<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema for the integration test suite (see IntegrationTestCase).
 *
 * This is a real migration -- rather than a handful of Schema::create() calls
 * in a test hook -- so that RefreshDatabase's own migrate step creates the
 * tables *before* it opens the per-test transaction. On MySQL, DDL implicitly
 * commits, so running it after the transaction has begun silently ends that
 * transaction, and the first DB::transaction() rollback inside a request then
 * fails with "SAVEPOINT trans2 does not exist" (SQLite hides this because its
 * DDL is transactional).
 */
return new class extends Migration
{
    public function up(): void
    {
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
            // Self-referencing linkable relationship (PetDefinition::linkedPet), used to
            // exercise sibling-to-sibling client references ('$ref') in bulk writes. No
            // FK constraint: the referenced pet may not exist yet when this row is first
            // inserted (forward reference within the same batch).
            $table->unsignedBigInteger('linked_pet_id')->nullable();
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

        // Self-referencing many-to-many pivot backing PetDefinition::relatedPets,
        // used to exercise the Cardinality::MANY client-reference drain branch
        // (PropertySetter::addChildren() -> BelongsToMany::attach()).
        Schema::create('related_pets', function (Blueprint $table) {
            $table->unsignedBigInteger('pet_id');
            $table->unsignedBigInteger('related_pet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_metadata');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('related_pets');
        Schema::dropIfExists('pets');
        Schema::dropIfExists('stores');
    }
};
