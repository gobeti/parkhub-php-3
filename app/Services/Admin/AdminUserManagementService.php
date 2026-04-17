<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Owns admin-side user-management flows extracted from AdminController
 * (T-1742, pass 5).
 *
 * Pure extraction — role changes still bypass mass-assignment via direct
 * property write + save(), token invalidation still fires on role or
 * password change, self-delete / self-bulk-action are still refused, and
 * the import path still pre-loads usernames + emails in two queries to
 * avoid N+1. Controllers stay responsible for FormRequest validation,
 * admin gating, HTTP shaping and Sanctum session rotation.
 *
 * Cross-tenant safety continues to ride on the global TenantScope on
 * Eloquent lookups (User::find / User::findOrFail), so an admin on one
 * tenant cannot reach a user row on another tenant from this service.
 * Writes that mutate state also emit an AuditLog entry.
 */
final class AdminUserManagementService
{
    /**
     * Paginate users for the admin user list. `perPage` is clamped to
     * [1, 100] by the caller before reaching this method.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function listUsers(int $perPage): LengthAwarePaginator
    {
        return User::paginate($perPage);
    }

    /**
     * Apply profile / role / password edits to a user.
     *
     * `role` is not mass-assignable (prevents privilege escalation via
     * mass-assignment), so it is written via a direct property assign
     * when provided. Whenever `role` OR `password` changes we invalidate
     * the target user's Sanctum tokens so a stale session cannot retain
     * the old privileges.
     *
     * Returns the fresh user plus flags describing which side effects
     * fired, so the controller can rotate its own session and emit audit.
     *
     * @param  array<string, mixed>  $payload  Already validated by UpdateUserRequest.
     * @return array{user: User, role_changed: bool, password_changed: bool}
     */
    public function updateUser(User $user, array $payload): array
    {
        $data = array_intersect_key(
            $payload,
            array_flip(['name', 'email', 'is_active', 'department']),
        );

        $passwordChanged = false;
        if (! empty($payload['password'])) {
            $data['password'] = Hash::make((string) $payload['password']);
            $passwordChanged = true;
        }

        if ($data !== []) {
            $user->update($data);
        }

        $roleChanged = false;
        if (! empty($payload['role']) && $user->role !== $payload['role']) {
            $user->role = (string) $payload['role'];
            $user->save();
            $roleChanged = true;
        }

        if ($roleChanged || $passwordChanged) {
            $user->tokens()->delete();
        }

        /** @var User $fresh */
        $fresh = $user->fresh();

        return [
            'user' => $fresh,
            'role_changed' => $roleChanged,
            'password_changed' => $passwordChanged,
        ];
    }

    /**
     * Delete a user. Returns false when the caller is trying to delete
     * their own account — the controller turns that into a 400.
     */
    public function deleteUser(User $target, User $actor): bool
    {
        if ($target->id === $actor->id) {
            return false;
        }

        $target->delete();

        return true;
    }

    /**
     * Bulk-import users, skipping any row whose username OR email already
     * exists. Returns the number of rows actually created.
     *
     * @param  array<int, array<string, mixed>>  $users  Already validated by ImportUsersRequest.
     */
    public function importUsers(array $users): int
    {
        $collection = collect($users);

        // Batch-check existing usernames + emails in 2 queries instead of N queries (closes #59).
        $existingUsernames = User::whereIn('username', $collection->pluck('username'))->pluck('username');
        $existingEmails = User::whereIn('email', $collection->pluck('email'))->pluck('email');

        $toImport = $collection->reject(
            fn ($u) => $existingUsernames->contains($u['username'])
                || $existingEmails->contains($u['email']),
        );

        $imported = 0;
        foreach ($toImport as $userData) {
            $user = User::create([
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password'] ?? Str::random(16)),
                'name' => $userData['name'] ?? $userData['username'],
                'is_active' => true,
                'department' => $userData['department'] ?? null,
                'preferences' => ['language' => 'en', 'theme' => 'system'],
            ]);
            $user->role = $userData['role'] ?? 'user';
            $user->save();
            $imported++;
        }

        return $imported;
    }

    /**
     * Bulk admin operation on a set of user IDs.
     *
     * Returns a per-user result array matching the legacy controller
     * shape, plus the summary counts. Self-destructive actions
     * (`deactivate`, `delete`) on the actor are reported as `skipped`.
     * An `admin_bulk_{action}` audit log entry is emitted once per call.
     *
     * @param  array<int, string>  $userIds  Already validated by BulkUserActionRequest.
     * @return array{
     *     action: string,
     *     results: array<int, array<string, mixed>>,
     *     total: int,
     *     successful: int,
     * }
     */
    public function bulkAction(
        string $action,
        array $userIds,
        User $actor,
        ?string $role = null,
        ?string $ip = null,
    ): array {
        $results = [];

        foreach ($userIds as $userId) {
            if ($userId === $actor->id && in_array($action, ['deactivate', 'delete'], true)) {
                $results[] = [
                    'user_id' => $userId,
                    'status' => 'skipped',
                    'reason' => 'Cannot perform this action on yourself',
                ];

                continue;
            }

            $user = User::find($userId);
            if (! $user) {
                $results[] = [
                    'user_id' => $userId,
                    'status' => 'failed',
                    'reason' => 'User not found',
                ];

                continue;
            }

            // FormRequest already restricts $action to the four cases below.
            // The default branch exists purely to keep PHPStan happy about
            // the exhaustiveness of the match expression.
            match ($action) {
                'activate' => $user->update(['is_active' => true]),
                'deactivate' => $user->update(['is_active' => false]),
                'change_role' => $this->applyRoleChange($user, (string) $role),
                'delete' => $user->delete(),
                default => null,
            };

            $results[] = ['user_id' => $userId, 'status' => 'success'];
        }

        AuditLog::log([
            'user_id' => $actor->id,
            'username' => $actor->username,
            'action' => 'admin_bulk_'.$action,
            'details' => ['count' => count($userIds)],
            'ip_address' => $ip,
        ]);

        return [
            'action' => $action,
            'results' => $results,
            'total' => count($results),
            'successful' => count(array_filter($results, fn ($r) => $r['status'] === 'success')),
        ];
    }

    /**
     * Apply a role change from the bulk path, invalidating existing
     * tokens so the new role takes effect on next login.
     */
    private function applyRoleChange(User $user, string $role): void
    {
        $user->role = $role;
        $user->save();
        $user->tokens()->delete();
    }
}
