<?php

use Illuminate\Foundation\Auth\User;
use Spatie\LaravelFlare\AttributesProviders\LaravelUserAttributesProvider;

it('returns the authenticated user', function () {
    $user = new User();
    $user->forceFill([
        'id' => 1,
        'email' => 'john@example.com',
    ]);

    $attributes = (new LaravelUserAttributesProvider($user))->toArray();

    expect($attributes['user.email'])->toBe('john@example.com');
    expect($attributes['user.id'])->toBe(1);
    expect($attributes)->not()->toHaveKeys([
        'user.full_name',
        'user.context',
    ]);
});

it('uses the toFlare method on the user when it exists', function () {
    $user = new class() extends User {
        public function toFlare()
        {
            return ['role' => 'admin'];
        }
    };

    $user->forceFill([
        'id' => 1,
        'email' => 'john@example.com',
    ]);

    $attributes = (new LaravelUserAttributesProvider($user))->toArray();

    expect($attributes['user.email'])->toBe('john@example.com');
    expect($attributes['user.id'])->toBe(1);
    expect($attributes['user.attributes'])->toBe(['role' => 'admin']);
    expect($attributes)->not()->toHaveKey('user.full_name');
});

it('returns no attributes when the user cannot be deduced', function ($user) {
    $attributes = (new LaravelUserAttributesProvider($user))->toArray();

    expect($attributes)->not()->toHaveKeys([
        'user.full_name',
        'user.email',
        'user.id',
        'user.attributes',
    ]);
})->with([
    'null' => fn () => null,
    'empty class' => fn () => new class() {
    },
    'empty user' => fn () => new User(),
    'array' => fn () => [],
]);
