<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    protected $signature = 'parkhub:create-admin
                            {--email= : Admin email address}
                            {--password= : Admin password}
                            {--username=admin : Admin username}';

    protected $description = 'Create default admin user if none exists';

    public function handle(): int
    {
        if (User::where('role', 'admin')->orWhere('role', 'superadmin')->count() > 0) {
            $this->info('Admin already exists');

            return self::SUCCESS;
        }

        $email = $this->option('email') ?: env('PARKHUB_ADMIN_EMAIL', 'admin@parkhub.test');
        $password = $this->option('password') ?: env('PARKHUB_ADMIN_PASSWORD', 'admin');
        $username = $this->option('username');

        $user = User::create([
            'id' => Str::uuid(),
            'username' => $username,
            'email' => $email,
            'password' => bcrypt($password),
            'name' => 'Admin',
            'is_active' => true,
            'preferences' => json_encode([
                'language' => 'en',
                'theme' => 'system',
                'notifications_enabled' => true,
            ]),
        ]);
        $user->role = 'admin';
        $user->save();

        Setting::set('needs_password_change', 'true');

        $this->info("Default admin created: {$email}");

        return self::SUCCESS;
    }
}
