<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LoginHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'login_history';

    protected $fillable = ['user_id', 'ip_address', 'user_agent', 'logged_in_at'];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
