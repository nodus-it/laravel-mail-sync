<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use NodusIT\LaravelMailSync\Models\MailAccount;
use NodusIT\LaravelMailSync\Services\MailAccountService;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->validMailAccountData = [
        'name' => 'Test Account',
        'email_address' => 'test@example.com',
        'host' => 'mail.example.com',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'test@example.com',
        'password' => 'password123',
        'is_active' => true,
    ];
});

describe('MailAccountService', function () {
    it('can create a mail account with valid data and successful connection', function () {
        // Mock ClientManager and Client
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('connect')->once();
        $mockClient->shouldReceive('getFolders')->once();
        $mockClient->shouldReceive('disconnect')->once();

        $mockClientManager = Mockery::mock(ClientManager::class);
        $mockClientManager->shouldReceive('make')->once()->andReturn($mockClient);

        $service = new MailAccountService($mockClientManager);
        $mailAccount = $service->create($this->validMailAccountData);

        expect($mailAccount)->toBeInstanceOf(MailAccount::class);
        expect($mailAccount->name)->toBe('Test Account');
        expect($mailAccount->email_address)->toBe('test@example.com');
        expect($mailAccount->host)->toBe('mail.example.com');
        expect($mailAccount->port)->toBe(993);
        expect($mailAccount->encryption)->toBe('ssl');
        expect($mailAccount->username)->toBe('test@example.com');
        expect($mailAccount->is_active)->toBe(true);
        expect($mailAccount->last_connection_failed_at)->toBeNull();
        expect($mailAccount->last_connection_error)->toBeNull();
    });

    it('throws validation exception for invalid email', function () {
        $invalidData = array_merge($this->validMailAccountData, [
            'email_address' => 'invalid-email',
        ]);

        $service = new MailAccountService;

        expect(fn () => $service->create($invalidData))
            ->toThrow(ValidationException::class);
    });

    it('throws validation exception for missing required fields', function () {
        $invalidData = [
            'name' => 'Test Account',
            // Missing required fields
        ];

        $service = new MailAccountService;

        expect(fn () => $service->create($invalidData))
            ->toThrow(ValidationException::class);
    });

    it('throws validation exception for invalid port', function () {
        $invalidData = array_merge($this->validMailAccountData, [
            'port' => 70000, // Invalid port number
        ]);

        $service = new MailAccountService;

        expect(fn () => $service->create($invalidData))
            ->toThrow(ValidationException::class);
    });

    it('throws validation exception for invalid encryption', function () {
        $invalidData = array_merge($this->validMailAccountData, [
            'encryption' => 'invalid-encryption',
        ]);

        $service = new MailAccountService;

        expect(fn () => $service->create($invalidData))
            ->toThrow(ValidationException::class);
    });

    it('throws exception when connection fails', function () {
        $mockClientManager = Mockery::mock(ClientManager::class);
        $mockClientManager->shouldReceive('make')
            ->once()
            ->andThrow(new ConnectionFailedException('Connection failed'));

        $service = new MailAccountService($mockClientManager);

        expect(fn () => $service->create($this->validMailAccountData))
            ->toThrow(Exception::class, 'IMAP connection failed: Connection failed');
    });

    it('can update an existing mail account', function () {
        // Create initial mail account
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('connect')->twice();
        $mockClient->shouldReceive('getFolders')->twice();
        $mockClient->shouldReceive('disconnect')->twice();

        $mockClientManager = Mockery::mock(ClientManager::class);
        $mockClientManager->shouldReceive('make')->twice()->andReturn($mockClient);

        $service = new MailAccountService($mockClientManager);
        $mailAccount = $service->create($this->validMailAccountData);

        // Update the mail account
        $updateData = [
            'name' => 'Updated Account Name',
            'port' => 143,
            'encryption' => 'tls',
        ];

        $updatedAccount = $service->update($mailAccount, $updateData);

        expect($updatedAccount->name)->toBe('Updated Account Name');
        expect($updatedAccount->port)->toBe(143);
        expect($updatedAccount->encryption)->toBe('tls');
        expect($updatedAccount->email_address)->toBe('test@example.com'); // Unchanged
        expect($updatedAccount->last_connection_failed_at)->toBeNull();
        expect($updatedAccount->last_connection_error)->toBeNull();
    });

    it('prevents duplicate email addresses', function () {
        // Create first mail account
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('connect')->once();
        $mockClient->shouldReceive('getFolders')->once();
        $mockClient->shouldReceive('disconnect')->once();

        $mockClientManager = Mockery::mock(ClientManager::class);
        $mockClientManager->shouldReceive('make')->once()->andReturn($mockClient);

        $service = new MailAccountService($mockClientManager);
        $service->create($this->validMailAccountData);

        // Try to create second account with same email
        $duplicateData = array_merge($this->validMailAccountData, [
            'name' => 'Duplicate Account',
        ]);

        expect(fn () => $service->create($duplicateData))
            ->toThrow(ValidationException::class);
    });

    it('can test existing connection successfully', function () {
        // Create mail account
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('connect')->twice();
        $mockClient->shouldReceive('getFolders')->twice();
        $mockClient->shouldReceive('disconnect')->twice();

        $mockClientManager = Mockery::mock(ClientManager::class);
        $mockClientManager->shouldReceive('make')->twice()->andReturn($mockClient);

        $service = new MailAccountService($mockClientManager);
        $mailAccount = $service->create($this->validMailAccountData);

        // Test connection
        $result = $service->testExistingConnection($mailAccount);

        expect($result)->toBe(true);
        expect($mailAccount->fresh()->last_connection_failed_at)->toBeNull();
        expect($mailAccount->fresh()->last_connection_error)->toBeNull();
    });

    it('handles connection failure when testing existing connection', function () {
        // Create mail account
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('connect')->once();
        $mockClient->shouldReceive('getFolders')->once();
        $mockClient->shouldReceive('disconnect')->once();

        $mockClientManager = Mockery::mock(ClientManager::class);
        $mockClientManager->shouldReceive('make')
            ->once()
            ->andReturn($mockClient);

        $service = new MailAccountService($mockClientManager);
        $mailAccount = $service->create($this->validMailAccountData);

        // Mock connection failure for test
        $mockClientManager->shouldReceive('make')
            ->once()
            ->andThrow(new ConnectionFailedException('Connection failed'));

        $result = $service->testExistingConnection($mailAccount);

        expect($result)->toBe(false);
        expect($mailAccount->fresh()->last_connection_failed_at)->toBeInstanceOf(\Carbon\Carbon::class);
        expect($mailAccount->fresh()->last_connection_error)->toContain('Connection failed');
    });

    it('validates encryption options correctly', function () {
        $validEncryptions = ['ssl', 'tls', 'starttls', 'none'];

        foreach ($validEncryptions as $encryption) {
            $mockClient = Mockery::mock(Client::class);
            $mockClient->shouldReceive('connect')->once();
            $mockClient->shouldReceive('getFolders')->once();
            $mockClient->shouldReceive('disconnect')->once();

            $mockClientManager = Mockery::mock(ClientManager::class);
            $mockClientManager->shouldReceive('make')->once()->andReturn($mockClient);

            $service = new MailAccountService($mockClientManager);

            $data = array_merge($this->validMailAccountData, [
                'email_address' => "test-{$encryption}@example.com",
                'encryption' => $encryption,
            ]);

            $mailAccount = $service->create($data);
            expect($mailAccount->encryption)->toBe($encryption);
        }
    });
});
