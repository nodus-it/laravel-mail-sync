<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use NodusIT\LaravelMailSync\Models\MailAccount;
use NodusIT\LaravelMailSync\Models\MailMessage;
use NodusIT\LaravelMailSync\Services\MailMessageService;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\Support\FlagCollection;
use Webklex\PHPIMAP\Support\FolderCollection;
use Webklex\PHPIMAP\Support\MessageCollection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mailAccount = MailAccount::factory()->create([
        'host' => 'imap.example.com',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'test@example.com',
        'password' => 'password123',
    ]);
});

describe('MailMessageService', function () {
    it('can sync messages from a mail account', function () {
        // Mock IMAP components
        $mockMessage = Mockery::mock(Message::class);
        $mockMessage->shouldReceive('getUid')->andReturn(12345);
        $mockMessage->shouldReceive('getMsgn')->andReturn(1);
        $mockMessage->shouldReceive('getMessageId')->andReturn('<test@example.com>');
        $mockMessage->shouldReceive('getSubject')->andReturn('Test Subject');
        $mockMessage->shouldReceive('getDate')->andReturn(now());
        $mockMessage->shouldReceive('getSize')->andReturn(1024);
        $mockMessage->shouldReceive('getFrom')->andReturn(collect([
            (object) ['mail' => 'sender@example.com', 'personal' => 'Sender Name']
        ]));
        $mockMessage->shouldReceive('getReplyTo')->andReturn(collect());
        $mockMessage->shouldReceive('getInReplyTo')->andReturn(null);
        $mockMessage->shouldReceive('getReferences')->andReturn(null);
        $mockMessage->shouldReceive('getTextBody')->andReturn('Test body text');
        $mockMessage->shouldReceive('getHTMLBody')->andReturn('<p>Test body HTML</p>');
        $mockMessage->shouldReceive('getRawHeader')->andReturn('Test headers');
        $mockMessage->shouldReceive('getFlags')->andReturn(new FlagCollection(['Seen']));
        $mockHeader = Mockery::mock(Header::class);
        $mockHeader->shouldReceive('get')->andReturn(new Attribute('test-header'));
        $mockMessage->shouldReceive('getHeader')->andReturn($mockHeader);

        $mockMessageCollection = new MessageCollection([$mockMessage]);

        $mockQuery = Mockery::mock(WhereQuery::class);
        $mockQuery->shouldReceive('all')->andReturn($mockQuery);
        $mockQuery->shouldReceive('limit')->with(10)->andReturn($mockQuery);
        $mockQuery->shouldReceive('get')->andReturn($mockMessageCollection);

        $mockFolder = Mockery::mock(Folder::class);
        $mockFolder->shouldReceive('messages')->andReturn($mockQuery);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('connect')->once();
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockFolder);
        $mockClient->shouldReceive('disconnect')->once();

        $mockClientManager = Mockery::mock(ClientManager::class);
        $mockClientManager->shouldReceive('make')->once()->andReturn($mockClient);

        $service = new MailMessageService($mockClientManager);
        $result = $service->syncMessages($this->mailAccount, 'INBOX', 10);

        expect($result)->toBeInstanceOf(Collection::class);
        expect($result)->toHaveCount(1);
        expect($result->first())->toBeInstanceOf(MailMessage::class);
        expect($result->first()->subject)->toBe('Test Subject');
        expect($result->first()->mail_account_id)->toBe($this->mailAccount->id);

        // Check that mail account was updated
        $this->mailAccount->refresh();
        expect($this->mailAccount->last_synced_at)->not->toBeNull();
    });

    it('handles connection failures gracefully', function () {
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('connect')->once()->andThrow(new ConnectionFailedException('Connection failed'));

        $mockClientManager = Mockery::mock(ClientManager::class);
        $mockClientManager->shouldReceive('make')->once()->andReturn($mockClient);

        $service = new MailMessageService($mockClientManager);

        expect(fn () => $service->syncMessages($this->mailAccount))
            ->toThrow(Exception::class, 'Failed to connect to mail server: Connection failed');

        // Check that connection error was recorded
        $this->mailAccount->refresh();
        expect($this->mailAccount->last_connection_failed_at)->not->toBeNull();
        expect($this->mailAccount->last_connection_error)->toContain('Connection failed');
    });

    it('can store a new message', function () {
        $mockMessage = createMockMessage();

        $service = new MailMessageService();
        $result = $service->storeMessage($this->mailAccount, $mockMessage);

        expect($result)->toBeInstanceOf(MailMessage::class);
        expect($result->mail_account_id)->toBe($this->mailAccount->id);
        expect($result->remote_uid)->toBe(12345);
        expect($result->subject)->toBe('Test Subject');
        expect($result->from_email)->toBe('sender@example.com');
        expect($result->from_name)->toBe('Sender Name');
        expect($result->is_seen)->toBe(true);
        expect($result->synced_at)->not->toBeNull();
    });

    it('updates existing message instead of creating duplicate', function () {
        // Create existing message
        $existingMessage = MailMessage::factory()->create([
            'mail_account_id' => $this->mailAccount->id,
            'remote_uid' => 12345,
            'is_seen' => false,
            'is_flagged' => false,
        ]);

        $mockMessage = createMockMessage([
            'flags' => new FlagCollection(['Seen', 'Flagged']),
        ]);

        $service = new MailMessageService();
        $result = $service->storeMessage($this->mailAccount, $mockMessage);

        expect($result->id)->toBe($existingMessage->id);
        expect($result->is_seen)->toBe(true);
        expect($result->is_flagged)->toBe(true);
        expect($result->synced_at)->not->toBeNull();

        // Ensure no duplicate was created
        expect(MailMessage::count())->toBe(1);
    });

    it('can get folders for a mail account', function () {
        $mockFolder1 = Mockery::mock();
        $mockFolder1->name = 'INBOX';
        $mockFolder1->full_name = 'INBOX';
        $mockFolder1->delimiter = '/';
        $mockFolder1->shouldReceive('hasChildren')->andReturn(false);

        $mockFolder2 = Mockery::mock();
        $mockFolder2->name = 'Sent';
        $mockFolder2->full_name = 'INBOX/Sent';
        $mockFolder2->delimiter = '/';
        $mockFolder2->shouldReceive('hasChildren')->andReturn(true);

        $mockFolders = new FolderCollection([$mockFolder1, $mockFolder2]);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('connect')->once();
        $mockClient->shouldReceive('getFolders')->andReturn($mockFolders);
        $mockClient->shouldReceive('disconnect')->once();

        $mockClientManager = Mockery::mock(ClientManager::class);
        $mockClientManager->shouldReceive('make')->once()->andReturn($mockClient);

        $service = new MailMessageService($mockClientManager);
        $result = $service->getFolders($this->mailAccount);

        expect($result)->toBeInstanceOf(Collection::class);
        expect($result)->toHaveCount(2);
        expect($result->first()['name'])->toBe('INBOX');
        expect($result->first()['has_children'])->toBe(false);
        expect($result->last()['name'])->toBe('Sent');
        expect($result->last()['has_children'])->toBe(true);
    });

    it('can get message count for a folder', function () {
        $mockUnseenQuery = Mockery::mock(WhereQuery::class);
        $mockUnseenQuery->shouldReceive('count')->andReturn(5);

        $mockQuery = Mockery::mock(WhereQuery::class);
        $mockQuery->shouldReceive('count')->andReturn(25);
        $mockQuery->shouldReceive('unseen')->andReturn($mockUnseenQuery);

        $mockFolder = Mockery::mock(Folder::class);
        $mockFolder->shouldReceive('messages')->andReturn($mockQuery);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('connect')->once();
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockFolder);
        $mockClient->shouldReceive('disconnect')->once();

        $mockClientManager = Mockery::mock(ClientManager::class);
        $mockClientManager->shouldReceive('make')->once()->andReturn($mockClient);

        $service = new MailMessageService($mockClientManager);
        $result = $service->getMessageCount($this->mailAccount, 'INBOX');

        expect($result)->toBe([
            'total' => 25,
            'unread' => 5,
            'read' => 20,
        ]);
    });

    it('handles sync errors for individual messages gracefully', function () {
        // Mock a message that will cause an error
        $mockBadMessage = Mockery::mock(Message::class);
        $mockBadMessage->shouldReceive('getUid')->andReturn(99999);
        $mockBadMessage->shouldReceive('getMessageId')->andThrow(new Exception('Invalid message'));

        // Mock a good message
        $mockGoodMessage = createMockMessage();

        $mockMessageCollection = new MessageCollection([$mockBadMessage, $mockGoodMessage]);

        $mockQuery = Mockery::mock(WhereQuery::class);
        $mockQuery->shouldReceive('all')->andReturn($mockQuery);
        $mockQuery->shouldReceive('get')->andReturn($mockMessageCollection);

        $mockFolder = Mockery::mock(Folder::class);
        $mockFolder->shouldReceive('messages')->andReturn($mockQuery);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('connect')->once();
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockFolder);
        $mockClient->shouldReceive('disconnect')->once();

        $mockClientManager = Mockery::mock(ClientManager::class);
        $mockClientManager->shouldReceive('make')->once()->andReturn($mockClient);

        $service = new MailMessageService($mockClientManager);
        $result = $service->syncMessages($this->mailAccount);

        // Should only sync the good message
        expect($result)->toHaveCount(1);
        expect($result->first()->remote_uid)->toBe(12345);
    });
});

// Helper function to create mock message
function createMockMessage(array $overrides = []): Message
{
    $defaults = [
        'uid' => 12345,
        'msgn' => 1,
        'messageId' => '<test@example.com>',
        'subject' => 'Test Subject',
        'date' => now(),
        'size' => 1024,
        'from' => collect([(object) ['mail' => 'sender@example.com', 'personal' => 'Sender Name']]),
        'replyTo' => collect(),
        'inReplyTo' => null,
        'references' => null,
        'textBody' => 'Test body text',
        'htmlBody' => '<p>Test body HTML</p>',
        'rawHeader' => 'Test headers',
        'flags' => new FlagCollection(['Seen']),
        'header' => null, // Will be created in the mock setup
    ];

    $data = array_merge($defaults, $overrides);

    $mockMessage = Mockery::mock(Message::class);
    $mockMessage->shouldReceive('getUid')->andReturn($data['uid']);
    $mockMessage->shouldReceive('getMsgn')->andReturn($data['msgn']);
    $mockMessage->shouldReceive('getMessageId')->andReturn($data['messageId']);
    $mockMessage->shouldReceive('getSubject')->andReturn($data['subject']);
    $mockMessage->shouldReceive('getDate')->andReturn($data['date']);
    $mockMessage->shouldReceive('getSize')->andReturn($data['size']);
    $mockMessage->shouldReceive('getFrom')->andReturn($data['from']);
    $mockMessage->shouldReceive('getReplyTo')->andReturn($data['replyTo']);
    $mockMessage->shouldReceive('getInReplyTo')->andReturn($data['inReplyTo']);
    $mockMessage->shouldReceive('getReferences')->andReturn($data['references']);
    $mockMessage->shouldReceive('getTextBody')->andReturn($data['textBody']);
    $mockMessage->shouldReceive('getHTMLBody')->andReturn($data['htmlBody']);
    $mockMessage->shouldReceive('getRawHeader')->andReturn($data['rawHeader']);
    $mockMessage->shouldReceive('getFlags')->andReturn($data['flags']);
    // Mock header with get() method
    $mockHeader = Mockery::mock(Header::class);
    $mockHeader->shouldReceive('get')->andReturn(new Attribute('test-header'));
    $mockMessage->shouldReceive('getHeader')->andReturn($mockHeader);

    return $mockMessage;
}
