<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VerificationCodeCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly User   $user,
      public readonly string $otp, public readonly string $context = 'registration')
    {
    }
}
