<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class Install extends Command
{
    protected $signature = 'parkhub:install';
    protected $description = 'Install ParkHub: create admin user and mark app as installed';

    public function handle()
    {
        $this->info('Installing ParkHub...');

        DB::table('settings')->updateOrInsert(
            ['key' => 'installed'],
            ['value' => '1', 'created_at' => now(), 'updated_at' => now()]
        );

        $email    = env('PARKHUB_ADMIN_EMAIL', 'admin@parkhub.local');
        $password = env('PARKHUB_ADMIN_PASSWORD', 'admin');

        DB::table('users')->updateOrInsert(
            ['email' => $email],
            [
                'username'          => 'admin',
                'name'              => 'Super Admin',
                'password'          => Hash::make($password),
                'role'              => 'admin',
                'email_verified_at' => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        $this->info('✓ Admin user created: ' . $email);
        $this->info('ParkHub installation complete!');

        return Command::SUCCESS;
    }
}
