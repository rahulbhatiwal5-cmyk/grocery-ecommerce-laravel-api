<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_routes_require_authentication(): void
    {
        $this->getJson('/api/addresses')->assertUnauthorized();
        $this->postJson('/api/addresses', $this->addressPayload())->assertUnauthorized();
        $this->getJson('/api/addresses/1')->assertUnauthorized();
        $this->patchJson('/api/addresses/1', ['city' => 'Pune'])->assertUnauthorized();
        $this->deleteJson('/api/addresses/1')->assertUnauthorized();
        $this->postJson('/api/addresses/1/default')->assertUnauthorized();
    }

    public function test_user_can_create_and_list_addresses_with_a_default_selection(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/addresses', $this->addressPayload(['is_default' => true]))
            ->assertCreated()
            ->assertJsonPath('data.full_name', 'Asha Mehta')
            ->assertJsonPath('data.city', 'Noida')
            ->assertJsonPath('data.is_default', true);

        $firstAddressId = $response->json('data.id');
        $this->postJson('/api/addresses', $this->addressPayload([
            'full_name' => 'Asha Mehta Office',
            'address_line_1' => 'Tower 5, Sector 62',
            'is_default' => false,
        ]))->assertCreated()->assertJsonPath('data.is_default', false);

        $this->assertDatabaseHas('addresses', [
            'id' => $firstAddressId,
            'user_id' => $user->id,
            'is_default' => true,
        ]);

        $this->getJson('/api/addresses/'.$firstAddressId)
            ->assertOk()
            ->assertJsonPath('data.id', $firstAddressId);

        $this->getJson('/api/addresses')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $firstAddressId);
    }

    public function test_user_can_update_delete_and_change_the_default_address(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->for($user)->create(['is_default' => true]);
        $second = Address::factory()->for($user)->create(['is_default' => false]);

        $this->actingAs($user, 'api')
            ->patchJson('/api/addresses/'.$second->id, [
                'address_line_2' => 'Near the metro station',
                'city' => 'Delhi',
            ])
            ->assertOk()
            ->assertJsonPath('data.city', 'Delhi')
            ->assertJsonPath('data.address_line_2', 'Near the metro station');

        $this->postJson('/api/addresses/'.$second->id.'/default')
            ->assertOk()
            ->assertJsonPath('data.id', $second->id)
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('addresses', ['id' => $first->id, 'is_default' => false]);
        $this->assertDatabaseHas('addresses', ['id' => $second->id, 'is_default' => true]);

        $this->deleteJson('/api/addresses/'.$second->id)->assertNoContent();

        $this->assertDatabaseMissing('addresses', ['id' => $second->id]);
    }

    public function test_user_cannot_read_or_mutate_another_users_address_and_untrusted_fields_are_rejected(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobsAddress = Address::factory()->for($bob)->create();

        $this->actingAs($alice, 'api');

        $this->getJson('/api/addresses/'.$bobsAddress->id)->assertNotFound();
        $this->patchJson('/api/addresses/'.$bobsAddress->id, ['city' => 'Mumbai'])->assertNotFound();
        $this->deleteJson('/api/addresses/'.$bobsAddress->id)->assertNotFound();
        $this->postJson('/api/addresses/'.$bobsAddress->id.'/default')->assertNotFound();
        $this->postJson('/api/addresses', $this->addressPayload(['user_id' => $bob->id]))->assertUnprocessable();

        $this->assertDatabaseCount('addresses', 1);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function addressPayload(array $overrides = []): array
    {
        return array_replace([
            'full_name' => 'Asha Mehta',
            'phone' => '+91 9876543210',
            'address_line_1' => 'A-1204, Sector 76',
            'address_line_2' => '',
            'city' => 'Noida',
            'state' => 'Uttar Pradesh',
            'postal_code' => '201301',
            'country' => 'India',
            'is_default' => false,
        ], $overrides);
    }
}
