<?php

namespace Tests\Unit\Policies;

use App\Models\Notification;
use App\Models\User;
use App\Policies\NotificationPolicy;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationPolicyTest extends TestCase
{
    private NotificationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new NotificationPolicy;
    }

    private function makeUser(string $role = 'user', ?string $tenantId = null): User
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->role = $role;
        $user->tenant_id = $tenantId;

        return $user;
    }

    private function makeNotification(string $userId): Notification
    {
        $n = new Notification;
        $n->user_id = $userId;

        return $n;
    }

    public function test_any_user_can_view_any(): void
    {
        $this->assertTrue($this->policy->viewAny($this->makeUser()));
    }

    public function test_recipient_can_view_notification(): void
    {
        $user = $this->makeUser();
        $notif = $this->makeNotification($user->id);

        $this->assertTrue($this->policy->view($user, $notif));
    }

    public function test_non_recipient_cannot_view_notification(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $notif = $this->makeNotification($owner->id);

        $this->assertFalse($this->policy->view($other, $notif));
    }

    public function test_platform_admin_can_view_any_notification(): void
    {
        $admin = $this->makeUser('superadmin');
        $owner = $this->makeUser();
        $notif = $this->makeNotification($owner->id);

        $this->assertTrue($this->policy->view($admin, $notif));
    }

    public function test_recipient_can_update_notification(): void
    {
        $user = $this->makeUser();
        $notif = $this->makeNotification($user->id);

        $this->assertTrue($this->policy->update($user, $notif));
    }

    public function test_non_recipient_cannot_update_notification(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $notif = $this->makeNotification($owner->id);

        $this->assertFalse($this->policy->update($other, $notif));
    }

    public function test_platform_admin_cannot_update_notification(): void
    {
        // Admins can read for support, but mutating someone else's
        // notifications (marking as read, deleting) is not permitted.
        $admin = $this->makeUser('superadmin');
        $owner = $this->makeUser();
        $notif = $this->makeNotification($owner->id);

        $this->assertFalse($this->policy->update($admin, $notif));
    }

    public function test_recipient_can_delete_notification(): void
    {
        $user = $this->makeUser();
        $notif = $this->makeNotification($user->id);

        $this->assertTrue($this->policy->delete($user, $notif));
    }

    public function test_non_recipient_cannot_delete_notification(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $notif = $this->makeNotification($owner->id);

        $this->assertFalse($this->policy->delete($other, $notif));
    }
}
