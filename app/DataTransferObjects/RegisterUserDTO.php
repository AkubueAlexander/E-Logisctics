<?php
namespace App\DataTransferObjects;

use App\Enums\UserRole;
use App\Http\Requests\Api\RegisterUserRequest;

class RegisterUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone_number,
        public readonly string $password,
        public readonly UserRole $role,
    ) {}

    public static function fromRequest(RegisterUserRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            email: $request->validated('email'),
            phone_number: $request->validated('phone_number'),
            password: $request->validated('password'),
            role: UserRole::from($request->validated('role')),
        );
    }
}
