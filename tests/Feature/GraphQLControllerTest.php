<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraphQLControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(): array
    {
        $user = User::factory()->create(['role' => 'user']);

        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    private function seedLot(): ParkingLot
    {
        return ParkingLot::create([
            'name' => 'GraphQL Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
    }

    public function test_graphql_playground_returns_html(): void
    {
        $response = $this->get('/api/v1/graphql/playground');

        $response->assertStatus(200);
        $this->assertStringContainsString('GraphiQL', $response->getContent());
        $this->assertStringContainsString('graphiql', $response->getContent());
    }

    public function test_graphql_me_query(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/v1/graphql', [
                'query' => '{ me { id name email role } }',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['me' => ['id', 'name', 'email', 'role']]]);
    }

    public function test_graphql_lots_query(): void
    {
        $this->seedLot();

        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/v1/graphql', [
                'query' => '{ lots { id name status } }',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['lots']]);

        $lots = $response->json('data.lots');
        $this->assertNotEmpty($lots);
        $this->assertEquals('GraphQL Test Lot', $lots[0]['name']);
    }

    public function test_graphql_lot_by_id_query(): void
    {
        $lot = $this->seedLot();

        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/v1/graphql', [
                'query' => "{ lot(id: {$lot->id}) { id name } }",
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.lot.name', 'GraphQL Test Lot');
    }

    public function test_graphql_empty_query_returns_400(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/v1/graphql', ['query' => '']);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_graphql_create_booking_mutation(): void
    {
        $lot = $this->seedLot();

        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/v1/graphql', [
                'query' => 'mutation { createBooking(lot_id: $lot_id) { id status } }',
                'variables' => ['lot_id' => $lot->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['createBooking' => ['id', 'status']]]);

        $this->assertEquals('confirmed', $response->json('data.createBooking.status'));
    }

    public function test_graphql_my_vehicles_query(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/v1/graphql', [
                'query' => '{ myVehicles { id license_plate } }',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['myVehicles']]);
    }
}
