<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Spatie\FlareClient\AttributesProviders\UserAttributesProvider;
use Throwable;

class LaravelUserAttributesProvider extends UserAttributesProvider
{
    public static function fromRequest(Request $request): ?self
    {
        try {
            $user = $request->user();
        } catch (Throwable) {
            return null;
        }

        if (! is_object($user)) {
            return null;
        }

        return new self($user);
    }

    public function id(): string|int|null
    {
        if ($this->user instanceof Authenticatable) {
            return $this->user->getAuthIdentifier();
        }

        if (is_object($this->user) && method_exists($this->user, 'getKey')) {
            return $this->user->getKey();
        }

        try {
            return $this->user->id ?? $this->user->uuid ?? $this->user->ulid ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function fullName(): ?string
    {
        try {
            return $this->user->name ?? $this->user->full_name ?? $this->user->fullName ?? $this->user->username ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function email(): ?string
    {
        try {
            return $this->user->email ?? $this->user->mail ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function attributes(): array
    {
        if (is_object($this->user) && method_exists($this->user, 'toFlare')) {
            return $this->user->toFlare();
        }

        return [];
    }
}
