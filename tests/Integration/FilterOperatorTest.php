<?php

namespace Tests\Integration;

use Tests\Integration\Models\Pet;

/**
 * Filter operators (CatLab\Charon\Enums\FilterOperator) run as Eloquent queries.
 */
class FilterOperatorTest extends IntegrationTestCase
{
    private function petNames(string $query): array
    {
        $response = $this->getJson('/api/pets?' . $query);
        $response->assertStatus(200);

        $names = array_map(function ($item) {
            return $item['name'];
        }, $response->json('items'));
        sort($names);

        return $names;
    }

    protected function seedPets(): void
    {
        $this->seedTestData();
        Pet::create(['name' => 'Nobody', 'status' => null]);
        Pet::create(['name' => 'Blank', 'status' => '']);
    }

    public function testAPlainValueFiltersOnEquality()
    {
        $this->seedPets();
        $this->assertSame(['Buddy', 'Luna'], $this->petNames('status=available'));
        $this->assertSame(['Buddy', 'Luna'], $this->petNames('status[op]=eq&status[value]=available'));
    }

    public function testNotEqualAndContains()
    {
        $this->seedPets();
        // SQL semantics: != leaves out null, an empty string is a value.
        $this->assertSame(['Blank', 'Max'], $this->petNames('status[op]=neq&status[value]=available'));
        $this->assertSame(['Buddy', 'Luna'], $this->petNames('status[op]=contains&status[value]=vail'));
    }

    public function testEmptyIsNullOrAnEmptyString()
    {
        $this->seedPets();
        $this->assertSame(['Blank', 'Nobody'], $this->petNames('status[op]=empty'));
    }

    public function testNotEmptyLeavesOutNullAndEmptyStrings()
    {
        $this->seedPets();
        $this->assertSame(['Buddy', 'Luna', 'Max'], $this->petNames('status[op]=not_empty'));
    }

    public function testAnUnknownOperatorFiltersNothing()
    {
        $this->seedPets();
        $this->assertCount(5, $this->petNames('status[op]=sounds_like&status[value]=x'));
    }
}
