<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_fillable_attributes(): void
    {
        $user = new User;
        $this->assertContains('username', $user->getFillable());
        $this->assertContains('email', $user->getFillable());
        $this->assertContains('password', $user->getFillable());
        $this->assertContains('name', $user->getFillable());
    }

    public function test_role_is_not_fillable(): void
    {
        $user = new User;
        $this->assertNotContains('role', $user->getFillable());
    }

    public function test_password_is_hidden(): void
    {
        $user = new User;
        $this->assertContains('password', $user->getHidden());
    }

    public function test_is_admin_returns_true_for_admin_role(): void
    {
        $user = new User;
        $user->role = 'admin';
        $this->assertTrue($user->isAdmin());
    }

    public function test_is_admin_returns_true_for_superadmin_role(): void
    {
        $user = new User;
        $user->role = 'superadmin';
        $this->assertTrue($user->isAdmin());
    }

    public function test_is_admin_returns_false_for_user_role(): void
    {
        $user = new User;
        $user->role = 'user';
        $this->assertFalse($user->isAdmin());
    }

    public function test_is_premium_returns_true_for_premium_role(): void
    {
        $user = new User;
        $user->role = 'premium';
        $this->assertTrue($user->isPremium());
    }

    public function test_is_premium_returns_false_for_user_role(): void
    {
        $user = new User;
        $user->role = 'user';
        $this->assertFalse($user->isPremium());
    }

    public function test_user_has_bookings_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->bookings());
    }

    public function test_user_has_vehicles_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->vehicles());
    }

    public function test_user_has_absences_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->absences());
    }

    public function test_user_has_favorites_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->favorites());
    }

    public function test_user_has_recurring_bookings_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->recurringBookings());
    }

    public function test_user_uses_uuid(): void
    {
        $user = User::factory()->create();
        $this->assertNotNull($user->id);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $user->id);
    }

    public function test_preferences_cast_to_array(): void
    {
        $user = User::factory()->create(['preferences' => ['language' => 'de', 'theme' => 'dark']]);
        $this->assertIsArray($user->preferences);
        $this->assertEquals('de', $user->preferences['language']);
    }

    public function test_user_soft_deletes(): void
    {
        $user = User::factory()->create();
        $user->delete();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNotNull(User::withTrashed()->find($user->id));
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->assertIsBool($user->is_active);
        $this->assertTrue($user->is_active);
    }
}
