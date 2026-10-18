<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalApiToken extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'token', 'expires_at'];

    protected $hidden = ['token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function isValid(int $marginMinutes = 2): bool
    {
        return $this->expires_at->greaterThan(now()->addMinutes($marginMinutes));
    }
}
