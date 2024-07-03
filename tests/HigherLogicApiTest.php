<?php

use FMASites\HigherLogicApi\HigherLogicApi;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class HigherLogicApiTest extends TestCase
{
    private $api;
    private $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->api = $this->getMockBuilder(HigherLogicApi::class)
            ->setConstructorArgs(['testUser', 'testPass'])
            ->setMethods(['callApi'])
            ->getMock();

        $this->api->client = $this->client;
    }

    public function testAuthenticateUser()
    {
        $response = new Response(200, [], json_encode([
            'LoginID' => 'login_id',
            'SessionID' => 'session_id',
            'UserID' => 'user_id'
        ]));

        $this->client->method('post')->willReturn($response);

        $this->api->authenticateUser();

        $this->assertEquals('login_id', $this->api->loginId);
        $this->assertEquals('session_id', $this->api->sessionId);
        $this->assertEquals('user_id', $this->api->userId);
    }

    public function testGetRecipientByEmail()
    {
        $recipientData = [
            (object) ['Email' => 'test@example.com', 'ID' => '123']
        ];

        $this->api->method('callApi')
            ->willReturn($recipientData);

        $recipient = $this->api->getRecipientByEmail('test@example.com');

        $this->assertEquals('123', $recipient->ID);
    }

    public function testAddToGroup()
    {
        $response = (object) ['Status' => 1];

        $this->api->method('callApi')
            ->willReturn($response);

        $result = $this->api->addToGroup('userId', 'groupId');

        $this->assertTrue($result);
    }

    public function testUpsertRecipientAdd()
    {
        $response = (object) ['ID' => 'new_recipient_id'];

        $this->api->method('callApi')
            ->willReturn($response);

        $result = $this->api->upsertRecipient('new@example.com', 'First', 'Last', '12345');

        $this->assertEquals('new_recipient_id', $result);
    }

    public function testUpsertRecipientUpdate()
    {
        $recipient = (object) ['ID' => 'existing_recipient_id'];

        $this->api->method('getRecipientByEmail')
            ->willReturn($recipient);

        $response = (object) ['ID' => 'existing_recipient_id'];

        $this->api->method('callApi')
            ->willReturn($response);

        $result = $this->api->upsertRecipient('existing@example.com', 'First', 'Last', '12345');

        $this->assertEquals('existing_recipient_id', $result);
    }
}
