---
title: Censoring collected data  
---

The Flare client collects a large amount of data within your application. You can configure this by configuring the Flare client in the `flare.php` config file.

We've initialised the config with the Flare defaults, but you can mix and match your own config.

## Anonymising IPs

By default, the Flare client collects information about the IP address of your application users. If you want to disable this information, you can set the `censor.client_ips` option to true:

```php
'censor' => [
  'client_ips' => true,
    // other config ...
],
```

## Censoring request/response body fields

When Flare collects information about a web request or response, the Flare client passes on any request/response fields present in the body.

Sometimes, such as on a login page, these request fields may contain a password you don't want to send to Flare.

To censor out values of specific fields, you can set the `censor.body_fields` config value. You should provide the names of the fields you wish to censor.

```php
'censor' => [
  'body_fields' => [
      'password',
      'password_confirmation',
  ],
    // other config ...
]
```

This will replace the value of any body fields named "password" with the value "<CENSORED>".

By default, Flare will censor the password and password_confirmation fields.

### Censoring nested body fields

If you have nested body fields that you want to censor, you can use dot notation to specify the fields:

```php
'censor' => [
    'body_fields' => [
        'user.password',
    ],
    // other config ...
]
```

You can also use an asterisk (*) as a wildcard to censor multiple fields at once:

```php
'censor' => [
    'body_fields' => [
        'users.*.password',
    ],
    // other config ...
]
```

## Censoring request/response headers

When Flare collects information about a web request or response, the Flare client passes on any request/response headers present.

Just like with the body fields, these headers can be censored. You can do this setting `censor.headers` in the Flare config:

```php
'censor' => [
  'headers' => [
      'API-KEY',
      'Authorization',
      'Cookie',
      'Set-Cookie',
      'X-CSRF-TOKEN',
      'X-XSRF-TOKEN',
  ],
    // other config ...
], 
```

When doing so, the value of the headers will be changed to "<CENSORED>" when sent to Flare.

By default, Flare will censor the following headers:

- API-KEY
- Authorization
- Cookie
- Set-Cookie
- X-CSRF-TOKEN
- X-XSRF-TOKEN

## Censoring user data

When a user logs in to your Laravel application and an error/trace occurs, helpful information about the user is sent to Flare.

By default, the following will be sent:

- id
- email
- name
- when the user model has a `toFlare` method, the data that method returns

When you don't want to send any user data, you can set the `EmptyUserAttributesProvider` as the user attribute provider in the Flare config:

```php
use Spatie\FlareClient\AttributesProviders\EmptyUserAttributesProvider;

'attribute_providers' => [
    'user' => EmptyUserAttributesProvider::class,
    // Other attribute providers ...
],
```


## Censoring cookies

When Flare collects information about a web request or response, the Flare client passes on any cookies present.

To censor all cookies, you can call `censorCookies` on the Flare Config:

```php
'censor' => [
  'cookies' => true,
  // other config ...
], 
```

## Censoring the current request session

When Flare collects information about a web request, the Flare client passes on any session data present.

To censor all session data, you can call `censorSession` on the Flare Config:

```php
'censor' => [
  'session' => true,
  // other config ...
], 
```
