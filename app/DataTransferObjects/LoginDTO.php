<?php

namespace App\DataTransferObjects;

use App\Http\Requests\Api\LoginRequest;

class LoginDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $device_name,
    ) {}

    public static function fromRequest(LoginRequest $request): self
    {
        return new self(
            email: $request->validated('email'),
            password: $request->validated('password'),
            device_name: $request->validated('device_name'),
        );
    }
}
