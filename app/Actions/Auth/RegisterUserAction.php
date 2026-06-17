<?php
namespace App\Actions\Auth;

use App\DataTransferObjects\RegisterUserDTO;
use App\Events\VerificationCodeCreated;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class RegisterUserAction
{
    public function __invoke(RegisterUserDTO $dto): User
    {
        $user = User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'phone_number' => $dto->phone_number,
            'password' => $dto->password, // Handled safely by your User model's 'hashed' cast
            'system_role' => $dto->role->value,
            'is_active' => false,
        ]);

        // Generate 6-Digit Mobile Verification Code
        $otp = random_int(100000, 999999);

        // Cache code mapped against email for 10 minutes
        Cache::put("email_otp:{$user->email}", $otp, now()->addMinutes(10));

        // dispatchOtpNotification($user, $otp);
        VerificationCodeCreated::dispatch($user, (string)$otp, 'Registration');
        return $user;
    }
}
