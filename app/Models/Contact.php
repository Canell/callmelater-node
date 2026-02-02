<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Account $account
 * @property-read string $full_name
 * @property-read string|null $primary_contact
 */
class Contact extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'email',
        'phone',
    ];

    protected $appends = ['full_name'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the full name attribute.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->first_name} {$this->last_name}")
        );
    }

    /**
     * Get the primary contact method (email preferred, fallback to phone).
     */
    protected function primaryContact(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->email ?? $this->phone
        );
    }
}
