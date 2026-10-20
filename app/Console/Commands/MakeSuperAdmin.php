<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * php artisan app:super-admin you@example.com
 *
 * Gives a user access to the Sharebility admin panel (/admin).
 * Creates the user first if they don't exist yet.
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'app:super-admin {email : Email address of the user}';

    protected $description = 'Give a user access to the admin panel, creating the user if needed';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("\"{$email}\" is not a valid email address.");

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->info("No user with {$email} yet, so let's create one.");

            $user = User::create([
                'name' => text('Name', required: true),
                'email' => $email,
                'password' => password('Password (at least 8 characters)', required: true, validate: fn (string $value) => strlen($value) < 8 ? 'Use at least 8 characters.' : null),
            ]);
        }

        $user->forceFill(['is_super_admin' => true])->save();

        $this->info("{$user->name} ({$user->email}) can now use the admin panel.");

        return self::SUCCESS;
    }
}
