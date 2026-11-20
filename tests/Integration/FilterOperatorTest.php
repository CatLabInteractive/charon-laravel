<?php

namespace Tests\Integration;

use CatLab\Charon\Exceptions\InvalidFilterException;
use CatLab\Charon\Laravel\Exceptions\CharonErrorHandler;
use Tests\Integration\Models\Pet;

/**
 * Filter conditions (CatLab\Charon\Enums\FilterOperator) run as Eloquent queries.
 */
class FilterOperatorTest extends IntegrationTestCase
{
    private function names(string $url): array
    {
        $response = $this->getJson($url);
        $response->assertStatus(200);

        $names = array_map(function ($item) {
            return $item['name'];
        }, $response->json('items'));
        sort($names);

        return $names;
    }

    private function petNames(string $query): array
    {
        return $this->names('/api/pets?' . $query);
    }

    protected function seedPets(): array
    {
        $data = $this->seedTestData();
        Pet::create(['name' => 'Nobody', 'status' => null]);
        Pet::create(['name' => 'Blank', 'status' => '']);
        Pet::create(['name' => '50% off', 'status' => 'sale']);
        Pet::create(['name' => 'a_b', 'status' => 'sale']);
        return $data;
    }

    public function testAPlainValueFiltersOnEquality()
    {
        $this->seedPets();
        $this->assertSame(['Buddy', 'Luna'], $this->petNames('status=available'));
        $this->assertSame(['Buddy', 'Luna'], $this->petNames('status[eq]=available'));
    }

    public function testNotEqualIncludesRowsWithoutAValue()
    {
        $this->seedPets();
        $this->assertSame(['50% off', 'Blank', 'Max', 'Nobody', 'a_b'], $this->petNames('status[neq]=available'));
    }

    public function testEmptyIsNullOrAnEmptyString()
    {
        $this->seedPets();
        $this->assertSame(['Blank', 'Nobody'], $this->petNames('status[empty]=1'));
    }

    public function testNotEmptyLeavesOutNullAndEmptyStrings()
    {
        $this->seedPets();
        $this->assertSame(['50% off', 'Buddy', 'Luna', 'Max', 'a_b'], $this->petNames('status[not_empty]=1'));
    }

    public function testConditionsCombine()
    {
        $this->seedPets();
        $this->assertSame(['50% off', 'Max', 'a_b'], $this->petNames('status[neq]=available&status[not_empty]=1'));
    }

    public function testConditionsWorkOnAChildCollection()
    {
        $data = $this->seedPets();
        $this->assertSame(['Max'], $this->names('/api/stores/' . $data['store1']->id . '/pets?status[neq]=available'));
        $this->assertSame(['Buddy'], $this->names('/api/stores/' . $data['store1']->id . '/pets?name[contains]=udd'));
    }

    public function testContainsMatchesWildcardsLiterally()
    {
        $this->seedPets();
        $this->assertSame(['50% off'], $this->petNames('name[contains]=' . urlencode('%')));
        $this->assertSame(['a_b'], $this->petNames('name[contains]=' . urlencode('_')));
        $this->assertSame(['Buddy'], $this->petNames('name[contains]=udd'));
        // a plain value on a searchable-only field searches, as before
        $this->assertSame(['Buddy'], $this->petNames('name=udd'));
    }

    public function testAnUnknownConditionIsRefusedWithA400()
    {
        $this->seedPets();
        $this->withoutExceptionHandling();

        try {
            $this->getJson('/api/pets?status[like]=x');
            $this->fail('Expected an InvalidFilterException');
        } catch (InvalidFilterException $e) {
            $response = (new CharonErrorHandler())->handleException(request(), $e);
            $this->assertSame(400, $response->getStatusCode());
            $this->assertStringContainsString('Unknown filter condition', $response->getContent());
        }
    }

    public function testContainsIsRefusedOnAFieldThatIsNotSearchable()
    {
        $this->seedPets();
        $this->withoutExceptionHandling();
        $this->expectException(InvalidFilterException::class);
        $this->getJson('/api/pets?status[contains]=vail');
    }

    public function testAnEntityFilterMethodKeepsEqualityButRefusesNewConditions()
    {
        $this->seedTestData();
        // Store::filterAddress() handles "=" itself (as a prefix match) ...
        $this->assertSame(['Pet Paradise'], $this->names('/api/stores?address=123'));

        // ... and would ignore the operator, so "is not" must not reach it.
        $this->withoutExceptionHandling();
        $this->expectException(InvalidFilterException::class);
        $this->getJson('/api/stores?address[neq]=123');
    }

    public function testAnEntityFilterMethodThatTakesAConditionFilterGetsEveryCondition()
    {
        $this->seedTestData();
        \Tests\Integration\Models\Store::$receivedNameFilters = [];

        $this->assertSame(['Animal Kingdom'], $this->names('/api/stores?name[neq]=' . urlencode('Pet Paradise')));
        $this->assertSame(['Animal Kingdom', 'Pet Paradise'], $this->names('/api/stores?name[not_empty]=1'));
        $this->assertSame(['Pet Paradise'], $this->names('/api/stores?name=' . urlencode('Pet Paradise')));

        $received = \Tests\Integration\Models\Store::$receivedNameFilters;
        $this->assertCount(3, $received);
        $this->assertSame('!=', $received[0]->getOperator());
        $this->assertSame('Pet Paradise', $received[0]->getValue());
        $this->assertFalse($received[1]->takesValue());
    }
}
