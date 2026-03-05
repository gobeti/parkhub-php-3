sh -c "php artisan optimize:clear && php artisan migrate --force && php -r \"require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); DB::table('settings')->updateOrInsert(['key'=>'installed'],['value'=>'1','created_at'=>now(),'updated_at'=>now()]); DB::table('users')->updateOrInsert(['email'=>'admin@parkhub.local'],['username'=>'admin','name'=>'Super Admin','password'=>password_hash('admin',PASSWORD_BCRYPT,['cost'=>12]),'created_at'=>now(),'updated_at'=>now()]);\" && php artisan serve --host=0.0.0.0 --port=\$PORT"<?php

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

        // Mark app as installed
        DB::table('settings')->updateOrInsert(
            ['key' => 'installed'],
            ['value' => '1', 'created_at' => now(), 'updated_at' => now()]
        );
        $this->info('✓ Settings: app marked as installed');

        // Create admin user
        $email    = env('PARKHUB_ADMIN_EMAIL', 'admin@parkhub.local');
        $password = env('PARKHUB_ADMIN_PASSWORD', 'admin');

        DB::table('users')->updateOrInsert(
            ['email' => $email],
            [
                'username'           => 'admin',
                'name'               => 'Super Admin',
                'password'           => Hash::make($password),
                'role'               => 'admin',
                'email_verified_at'  => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]
        );
        $this->info('✓ Admin user created: ' . $email);

        $this->info('ParkHub installation complete!');

        return Command::SUCCESS;
    }
}
