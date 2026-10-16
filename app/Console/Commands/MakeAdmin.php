<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;

class MakeAdmin extends Command
{
    protected $signature = 'app:make-admin {email : Email of the account to promote or create}';

    protected $description = 'Give an account access to the Filament admin panel, creating it if needed';

    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user !== null) {
            $user->forceFill(['is_admin' => true])->save();
            $this->info("{$email} is now an admin.");

            if ($user->password === null) {
                $this->warn('This account has no password (Google-only). Set one with the app\'s "forgot password" flow to log in to /admin.');
            }

            return self::SUCCESS;
        }

        $password = $this->input->isInteractive()
            ? password(label: 'Password for the new admin', required: true, validate: fn (string $value) => strlen($value) < 8 ? 'At least 8 characters.' : null)
            : Str::password(20);

        $user = new User(['name' => Str::before($email, '@'), 'email' => $email, 'password' => $password]);
        $user->forceFill(['email_verified_at' => now(), 'is_admin' => true])->save();

        $this->info("Created admin {$email}.");

        if (! $this->input->isInteractive()) {
            $this->line("Generated password: {$password}");
        }

        return self::SUCCESS;
    }
}
