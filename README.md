# This is my package laravel-mail-sync

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nodus-it/laravel-mail-sync.svg?style=flat-square)](https://packagist.org/packages/nodus-it/laravel-mail-sync)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/nodus-it/laravel-mail-sync/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/nodus-it/laravel-mail-sync/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/nodus-it/laravel-mail-sync/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/nodus-it/laravel-mail-sync/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/nodus-it/laravel-mail-sync.svg?style=flat-square)](https://packagist.org/packages/nodus-it/laravel-mail-sync)

Simple Laravel package to sync emails from an IMAP server to your application.

## Installation

You can install the package via composer:

```bash
composer require nodus-it/laravel-mail-sync
```

You can run the migrations with:

```bash
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-mail-sync-config"
```

Optionally, you can publish the views using

## Usage

### MailAccountService

The `MailAccountService` allows you to create, update, and manage IMAP mail accounts with automatic validation and connection testing.

#### Creating a Mail Account

```php
$mailAccount = NodusIT\LaravelMailSync\Facades\MailAccount::create([
    'name' => 'My Account',
    'email_address' => 'user@mydomain.com',
    'host' => 'mail.mydomain.com',
    'port' => 993,
    'encryption' => 'ssl', // Options: ssl, tls, starttls, none
    'username' => 'user@mydomain.com',
    'password' => 'your-app-password',
    'is_active' => true,
]);
```

#### Updating a Mail Account

```php
$mailAccount = NodusIT\LaravelMailSync\Models\MailAccount::find(1);

$updatedAccount = NodusIT\LaravelMailSync\Facades\MailAccount::update($mailAccount, [
    'name' => 'Updated Account Name',
    'port' => 143,
    'encryption' => 'starttls',
]);
```

#### Testing Connection

```php
$mailAccount = NodusIT\LaravelMailSync\Models\MailAccount::find(1);

$connectionSuccessful = NodusIT\LaravelMailSync\Facades\MailAccount::testExistingConnection($mailAccount);

if ($connectionSuccessful) {
    echo "Connection successful!";
} else {
    echo "Connection failed: " . $mailAccount->last_connection_error;
}
```

#### Error Handling

The service automatically validates input data and tests IMAP connections:

```php
try {
    $mailAccount = NodusIT\LaravelMailSync\Facades\MailAccount::create($accountData);
} catch (\Illuminate\Validation\ValidationException $e) {
    // Handle validation errors
    $errors = $e->validator->errors();
} catch (\Exception $e) {
    // Handle connection or other errors
    echo "Error: " . $e->getMessage();
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Bastian Schur](https://github.com/bastian-schur)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
