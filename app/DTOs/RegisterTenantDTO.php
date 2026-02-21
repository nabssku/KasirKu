<?php

namespace App\DTOs;

class RegisterTenantDTO
{
    public function __construct(
        public string $tenant_name,
        public string $owner_name,
        public string $email,
        public string $password,
        public ?string $domain = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            tenant_name: $data['tenant_name'],
            owner_name: $data['owner_name'],
            email: $data['email'],
            password: $data['password'],
            domain: $data['domain'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'tenant_name' => $this->tenant_name,
            'owner_name' => $this->owner_name,
            'email' => $this->email,
            'password' => $this->password,
            'domain' => $this->domain,
        ];
    }
}
