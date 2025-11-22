<?php

namespace NodusIT\LaravelMailSync\Services;

use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use NodusIT\LaravelMailSync\Models\MailAccount;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class MailAccountService
{
    protected ClientManager $clientManager;

    public function __construct(ClientManager $clientManager = null)
    {
        $this->clientManager = $clientManager ?? new ClientManager([]);
    }

    /**
     * Create a new mail account with validation and connection testing
     *
     * @param array $data
     * @return MailAccount
     * @throws ValidationException
     * @throws Exception
     */
    public function create(array $data): MailAccount
    {
        // Validate input data
        $this->validateForCreate($data);

        // Test connection before creating
        $this->testConnection($data);

        // Create the mail account
        $mailAccount = MailAccount::create([
            'name' => $data['name'],
            'email_address' => $data['email_address'],
            'host' => $data['host'],
            'port' => $data['port'],
            'encryption' => $data['encryption'],
            'username' => $data['username'],
            'password' => $data['password'],
            'is_active' => $data['is_active'] ?? true,
            'last_connection_failed_at' => null,
            'last_connection_error' => null,
        ]);

        return $mailAccount;
    }

    /**
     * Update an existing mail account with validation and connection testing
     *
     * @param MailAccount $mailAccount
     * @param array $data
     * @return MailAccount
     * @throws ValidationException
     * @throws Exception
     */
    public function update(MailAccount $mailAccount, array $data): MailAccount
    {
        // Validate input data
        $this->validateForUpdate($data, $mailAccount->id);

        // Test connection with new data
        $connectionData = array_merge($mailAccount->getAttributes(), $data);
        $this->testConnection($connectionData);

        // Update the mail account
        $mailAccount->update(array_merge($data, [
            'last_connection_failed_at' => null,
            'last_connection_error' => null,
        ]));

        return $mailAccount->fresh();
    }

    /**
     * Validate mail account data for creation
     *
     * @param array $data
     * @throws ValidationException
     */
    protected function validateForCreate(array $data): void
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email_address' => [
                'required',
                'email',
                'max:255',
                'unique:mail_accounts,email_address'
            ],
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'encryption' => 'required|in:ssl,tls,starttls,none',
            'username' => 'required|string|max:255',
            'password' => 'required|string|min:1',
            'is_active' => 'sometimes|boolean',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Validate mail account data for update
     *
     * @param array $data
     * @param int $excludeId
     * @throws ValidationException
     */
    protected function validateForUpdate(array $data, int $excludeId): void
    {
        $rules = [
            'name' => 'sometimes|string|max:255',
            'email_address' => [
                'sometimes',
                'email',
                'max:255',
                "unique:mail_accounts,email_address,$excludeId"
            ],
            'host' => 'sometimes|string|max:255',
            'port' => 'sometimes|integer|min:1|max:65535',
            'encryption' => 'sometimes|in:ssl,tls,starttls,none',
            'username' => 'sometimes|string|max:255',
            'password' => 'sometimes|string|min:1',
            'is_active' => 'sometimes|boolean',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Test IMAP connection using Webklex ClientManager
     *
     * @param array $connectionData
     * @throws Exception
     */
    protected function testConnection(array $connectionData): void
    {
        try {
            $client = $this->clientManager->make([
                'host' => $connectionData['host'],
                'port' => $connectionData['port'],
                'encryption' => $connectionData['encryption'],
                'validate_cert' => true,
                'username' => $connectionData['username'],
                'password' => $connectionData['password'],
                'protocol' => 'imap'
            ]);

            // Try to connect and get folders to verify the connection works
            $client->connect();
            $client->getFolders();
            $client->disconnect();

        } catch (ConnectionFailedException $e) {
            throw new Exception('IMAP connection failed: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            throw new Exception('Failed to establish IMAP connection: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Test connection for an existing mail account and update connection status
     *
     * @param MailAccount $mailAccount
     * @return bool
     */
    public function testExistingConnection(MailAccount $mailAccount): bool
    {
        try {
            $connectionData = $mailAccount->getAttributes();
            $this->testConnection($connectionData);

            $mailAccount->update([
                'last_connection_failed_at' => null,
                'last_connection_error' => null,
            ]);

            return true;
        } catch (Exception $e) {
            $mailAccount->update([
                'last_connection_failed_at' => now(),
                'last_connection_error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
