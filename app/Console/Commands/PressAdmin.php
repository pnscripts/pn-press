<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Press\Permissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Creates (or promotes) an admin account, for sites installed without the demo seed.
 * The password is asked for interactively and never shown or logged.
 */
class PressAdmin extends Command
{
    protected $signature = 'press:admin {email : Email address to sign in with} {--name=Admin : Display name} {--role=admin : admin or editor}';

    protected $description = 'Create an admin (or editor) account, or give an existing user that role';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $role = (string) $this->option('role');

        if (Validator::make(['email' => $email], ['email' => 'required|email|max:255'])->fails() || ! in_array($role, ['admin', 'editor'], true)) {
            $this->error('Give a valid email address and --role=admin or --role=editor.');

            return self::FAILURE;
        }

        Permissions::sync();
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $password = (string) $this->secret('Password (at least 10 characters)');

            if (mb_strlen($password) < 10 || $password !== (string) $this->secret('Repeat the password')) {
                $this->error('The passwords are shorter than 10 characters or do not match. Nothing was created.');

                return self::FAILURE;
            }

            $user = User::query()->create(['name' => (string) $this->option('name'), 'email' => $email, 'password' => $password]);
        }

        $user->assignRole($role);
        $this->info("{$email} can now sign in at ".url('/admin')." as {$role}.");

        return self::SUCCESS;
    }
}
