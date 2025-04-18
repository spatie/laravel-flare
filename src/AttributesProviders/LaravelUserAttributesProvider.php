<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\FlareClient\AttributesProviders\UserAttributesProvider;

class LaravelUserAttributesProvider extends UserAttributesProvider
{
    public function id(mixed $user): string|int|null
    {
        if ($user instanceof Authenticatable) {
            return $user->getAuthIdentifier();
        }

        if (is_object($user) && method_exists($user, 'getKey')) {
            return $user->getKey();
        }

        try {
            return $user->id ?? $user->uuid ?? $user->ulid ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function fullName(mixed $user): string|null
    {
        try {
            return $user->name ?? $user->full_name ?? $user->fullName ?? $user->username ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function email(mixed $user): string|null
    {
        try {
            return $user->email ?? $user->mail ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function attributes(mixed $user): array
    {
        if (is_object($user) && method_exists($user, 'toFlare')) {
            return $user->toFlare();
        }

        return [];
    }
}
