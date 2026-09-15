<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    public function test_creates_order_by_sku(): void
    {
        $this->seedProduct();

        $response = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500']);

        $response->assertCreated()
            ->assertJsonPath('sku', 'STEAM-TOPUP-500')
            ->assertJsonPath('status', OrderStatus::Created->value)
            ->assertJsonPath('amount', 500)
            ->assertJsonPath('amount_cents', 50_000)
            ->assertJsonPath('code', null);

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_unknown_sku_returns_404(): void
    {
        $this->postJson('/api/orders', ['sku' => 'NO-SUCH'])->assertNotFound();
    }

    public function test_out_of_stock_returns_422(): void
    {
        $this->seedProduct(['stock_qty' => 0, 'is_available' => false], 0);

        $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])
            ->assertStatus(422);
    }

    public function test_shows_order_by_id(): void
    {
        $this->seedProduct();
        $create = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])->json();

        $this->getJson('/api/orders/'.$create['id'])
            ->assertOk()
            ->assertJsonPath('id', $create['id'])
            ->assertJsonPath('status', 'created');
    }
}
