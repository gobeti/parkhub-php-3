<?php

namespace Tests\Unit\Models;

use App\Models\CreditTransaction;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditTransactionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_transaction_has_fillable_attributes(): void
    {
        $model = new CreditTransaction;
        $this->assertContains('user_id', $model->getFillable());
        $this->assertContains('booking_id', $model->getFillable());
        $this->assertContains('amount', $model->getFillable());
        $this->assertContains('type', $model->getFillable());
        $this->assertContains('description', $model->getFillable());
        $this->assertContains('granted_by', $model->getFillable());
    }

    public function test_amount_cast_to_integer(): void
    {
        $model = new CreditTransaction;
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['amount']);
    }

    public function test_belongs_to_user(): void
    {
        $model = new CreditTransaction;
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_belongs_to_booking(): void
    {
        $model = new CreditTransaction;
        $this->assertInstanceOf(BelongsTo::class, $model->booking());
    }
}
