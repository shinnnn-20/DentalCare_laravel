<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

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

        $password = $this->secret('Administrator password (minimum 8 characters, one capital letter, and one special character)');
        $passwordValidator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::defaults()]],
        );

        if ($passwordValidator->fails()) {
            $this->components->error($passwordValidator->errors()->first('password'));

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
