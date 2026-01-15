<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MagicLinkToken extends Model
{
    public const PURPOSE_LOGIN = 'login';
    public const PURPOSE_SIGNUP = 'signup';

    protected $fillable = [
        'email',
        'token',
        'purpose',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }

    public function markUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    public function isForSignup(): bool
    {
        return $this->purpose === self::PURPOSE_SIGNUP;
    }

    public function isForLogin(): bool
    {
        return $this->purpose === self::PURPOSE_LOGIN;
    }
}
