<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SystemUserSeeder extends Seeder
{
    public const SYSTEM_EMAIL = 'system@callmelater.internal';

    public function run(): void
    {
        // Check if system user already exists
        $existingUser = User::where('email', self::SYSTEM_EMAIL)->first();

        if ($existingUser) {
            $this->command->info('System user already exists: '.self::SYSTEM_EMAIL);

            return;
        }

        // Create system user first (without account)
        $user = User::create([
            'email' => self::SYSTEM_EMAIL,
            'name' => 'CallMeLater System',
            'password' => bcrypt(Str::random(64)), // Random password, never used
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);

        // Create system account with user as owner
        $account = Account::create([
            'name' => 'CallMeLater System',
            'owner_id' => $user->id,
        ]);

        // Update user with account_id
        $user->update(['account_id' => $account->id]);

        // Add user to account members as owner
        $account->members()->attach($user->id, ['role' => 'owner']);

        $this->command->info('System user created: '.self::SYSTEM_EMAIL);
    }

    /**
     * Get the system user (for use in other parts of the app).
     */
    public static function getSystemUser(): User
    {
        $user = User::where('email', self::SYSTEM_EMAIL)->first();

        if (! $user) {
            throw new \RuntimeException(
                'System user not found. Run: php artisan db:seed --class=SystemUserSeeder'
            );
        }

        return $user;
    }
}
