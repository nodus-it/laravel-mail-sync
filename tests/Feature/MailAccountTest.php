<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use NodusIT\LaravelMailSync\Models\MailAccount;

uses(RefreshDatabase::class);

it('can create a mail account', function () {
    $mailAccount = MailAccount::factory()->create([
        'name' => 'Test Mail Account',
        'email_address' => 'test@example.com',
        'host' => 'mail.example.com',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'testuser',
        'password' => 'testpassword',
        'is_active' => true,
    ]);

    expect($mailAccount->name)->toBe('Test Mail Account');
    expect($mailAccount->email_address)->toBe('test@example.com');
    expect($mailAccount->is_active)->toBeTrue();

    $this->assertDatabaseHas('mail_accounts', [
        'name' => 'Test Mail Account',
        'email_address' => 'test@example.com',
        'host' => 'mail.example.com',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'testuser',
        'is_active' => true,
    ]);
});

it('hides password in array representation', function () {
    $mailAccount = MailAccount::factory()->create([
        'password' => 'secret-password',
    ]);

    $array = $mailAccount->toArray();

    expect($array)->not->toHaveKey('password');
});

it('casts attributes correctly', function () {
    $mailAccount = MailAccount::factory()->create([
        'is_active' => 1,
        'last_connection_failed' => 0,
        'port' => '993',
        'last_synced_at' => '2023-01-01 12:00:00',
    ]);

    expect($mailAccount->is_active)->toBeBool();
    expect($mailAccount->last_connection_failed)->toBeBool();
    expect($mailAccount->port)->toBeInt();
    expect($mailAccount->last_synced_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('can create active mail account using factory state', function () {
    $mailAccount = MailAccount::factory()->active()->create();

    expect($mailAccount->is_active)->toBeTrue();
    expect($mailAccount->last_connection_failed)->toBeFalse();
    expect($mailAccount->last_connection_error)->toBeNull();
});

it('can create inactive mail account using factory state', function () {
    $mailAccount = MailAccount::factory()->inactive()->create();

    expect($mailAccount->is_active)->toBeFalse();
});

it('can create mail account with connection error using factory state', function () {
    $mailAccount = MailAccount::factory()->withConnectionError()->create();

    expect($mailAccount->last_connection_failed)->toBeTrue();
    expect($mailAccount->last_connection_error)->not->toBeNull();
});

it('has correct fillable attributes', function () {
    $mailAccount = new MailAccount;

    $expectedFillable = [
        'name',
        'email_address',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'is_active',
        'last_synced_at',
        'last_connection_error',
        'last_connection_failed',
    ];

    expect($mailAccount->getFillable())->toBe($expectedFillable);
});
