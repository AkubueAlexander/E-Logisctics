<?php

namespace App\Actions\Store;

use App\Models\User;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InviteRepresentative
{
    public function execute(Store $store, array $data): User
    {
        return DB::transaction(function () use ($store, $data) {

            // 1. Create the User with their global identity role
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt(Str::random(32)),
                'system_role' => $data['system_role'], // Dynamic from payload
            ]);

            // 2. Attach them to the specific store with their local branch role
            $store->users()->attach($user->id, [
                'role' => $data['store_role'] // Dynamic from payload ('staff' or 'manager')
            ]);

            // 3. Trigger Password Reset Email
            Password::broker()->sendResetLink(['email' => $user->email]);

            return $user;
        });
    }
}
