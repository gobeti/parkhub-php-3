<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'vapid:generate {--force : Overwrite existing keys}';

    protected $description = 'Generate VAPID keys for Web Push notifications';

    public function handle(): int
    {
        $existing = Setting::get('vapid_public_key');
        if ($existing && ! $this->option('force')) {
            $this->info('VAPID keys already exist. Use --force to regenerate.');

            return 0;
        }

        $keys = VAPID::createVapidKeys();

        Setting::set('vapid_public_key', $keys['publicKey']);
        Setting::set('vapid_private_key', $keys['privateKey']);

        $this->info('VAPID keys generated and saved to settings.');
        $this->line('Public key: '.$keys['publicKey']);

        return 0;
    }
}
