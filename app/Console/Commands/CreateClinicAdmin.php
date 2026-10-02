<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('clinic:create-admin {name} {email}')]
#[Description('Create the clinic owner account when no administrator exists')]
class CreateClinicAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (User::where('role', 'admin')->exists()) {
            $this->components->error('A clinic administrator already exists.');

            return self::FAILURE;
        }

        $email = mb_strtolower((string) $this->argument('email'));
        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255', 'unique:users,email']]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        $password = $this->secret('Administrator password (minimum 12 characters)');

        if (! is_string($password) || mb_strlen($password) < 12) {
            $this->components->error('The administrator password must be at least 12 characters.');

            return self::FAILURE;
        }

        User::create([
            'name' => (string) $this->argument('name'),
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->components->info('Clinic administrator account created.');

        return self::SUCCESS;
    }
}
