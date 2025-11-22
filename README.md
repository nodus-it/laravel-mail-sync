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

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-mail-sync-migrations"
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

### MailMessageService

The `MailMessageService` allows you to sync email messages from IMAP servers and store them in your database with full message content and metadata.

#### Syncing Messages from a Mail Account

```php
use NodusIT\LaravelMailSync\Models\MailAccount as MailAccountModel;
use NodusIT\LaravelMailSync\Facades\MailMessage;

$mailAccount = MailAccountModel::find(1);

// Sync all messages from INBOX
$syncedMessages = MailMessage::syncMessages($mailAccount);

// Sync messages from a specific folder with limit
$syncedMessages = MailMessage::syncMessages($mailAccount, 'INBOX/Sent', 50);

echo "Synced " . $syncedMessages->count() . " messages";
```

#### Getting Available Folders

```php
$mailAccount = MailAccountModel::find(1);

$folders = MailMessage::getFolders($mailAccount);

foreach ($folders as $folder) {
    echo "Folder: " . $folder['name'] . " (" . $folder['full_name'] . ")\n";
    echo "Has children: " . ($folder['has_children'] ? 'Yes' : 'No') . "\n";
}
```

#### Getting Message Counts

```php
$mailAccount = MailAccountModel::find(1);

$counts = MailMessage::getMessageCount($mailAccount, 'INBOX');

echo "Total messages: " . $counts['total'] . "\n";
echo "Unread messages: " . $counts['unread'] . "\n";
echo "Read messages: " . $counts['read'] . "\n";
```

#### Working with Synced Messages

```php
use NodusIT\LaravelMailSync\Models\MailMessage as MailMessageModel;

// Get all messages for an account
$messages = MailMessageModel::forAccount($mailAccount->id)->get();

// Get unread messages
$unreadMessages = MailMessageModel::forAccount($mailAccount->id)->unread()->get();

// Get flagged messages
$flaggedMessages = MailMessageModel::forAccount($mailAccount->id)->flagged()->get();

// Access message content
foreach ($messages as $message) {
    echo "Subject: " . $message->subject . "\n";
    echo "From: " . $message->from_name . " <" . $message->from_email . ">\n";
    echo "Body Preview: " . $message->body_preview . "\n";
    echo "Is Read: " . ($message->is_seen ? 'Yes' : 'No') . "\n";
    echo "---\n";
}
```

#### Error Handling for Message Sync

```php
try {
    $syncedMessages = MailMessage::syncMessages($mailAccount, 'INBOX', 100);
    echo "Successfully synced " . $syncedMessages->count() . " messages";
} catch (\Exception $e) {
    echo "Sync failed: " . $e->getMessage();
    
    // Check account for connection errors
    $mailAccount->refresh();
    if ($mailAccount->last_connection_error) {
        echo "Connection error: " . $mailAccount->last_connection_error;
    }
}
```

#### Using the Service Directly

```php
use NodusIT\LaravelMailSync\Services\MailMessageService;

$service = new MailMessageService();

// Sync messages
$syncedMessages = $service->syncMessages($mailAccount, 'INBOX', 25);

// Get folders
$folders = $service->getFolders($mailAccount);

// Get message count
$counts = $service->getMessageCount($mailAccount, 'INBOX');
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
