---
title: Identifying users 
---


When a user logs in to your Laravel application and an error/trace occurs, helpful information about the user is sent to Flare.

By default, the following will be sent:

- id
- email
- name

When you implement the `toFlare` method on the user model, additional attributes can be sent:

```php
class User extends BaseUser
{
    public function toFlare()
    {
        return [
            'name' => $this->name,
            'email' => $this->email
        ];
    }
}
```

It is possible to customise which data will be used for the current user by implementing a custom `UserAttributesProvider`:

```php
use Spatie\FlareClient\AttributesProviders\ UserAttributesProvider;

class CustomUserAttributesProvider extends UserAttributesProvider
{
    public function id(mixed $user): string|int|null
    {
        return $user->id;
    }

    public function fullName(mixed $user): string|null
    {
        return "{$user->first_name} {$user->last_name}";
    }

    public function email(mixed $user): string|null
    {
        return $user->email;
    }

    public function attributes(mixed $user): array
    {
        return [
            'team_id' => $user->team_id
        ];
    }
}
```

The custom provider then should be registered within the Flare config as such:

```php
'attribute_providers' => [
    'user' => CustomUserAttributesProvider::class,
    // Other attribute providers ...
],
```

When you don't want to send any user data, you can use the `EmptyUserAttributesProvider`:

```php
use Spatie\FlareClient\AttributesProviders\EmptyUserAttributesProvider;

'attribute_providers' => [
    'user' => EmptyUserAttributesProvider::class,
    // Other attribute providers ...
],
``` 