<?php

namespace FMASites\HigherLogicApi\Tests;

use FMASites\HigherLogicApi\HigherLogicApi;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class HigherLogicApiTest extends TestCase
{
    private $api;
    private $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->api = new HigherLogicApi('testUser', 'testPass');
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

        $partialMock = $this->getMockBuilder(HigherLogicApi::class)
            ->setConstructorArgs(['testUser', 'testPass'])
            ->onlyMethods(['callApi'])
            ->getMock();

        $partialMock->method('callApi')
            ->willReturn($recipientData);

        $recipient = $partialMock->getRecipientByEmail('test@example.com');

        $this->assertEquals('123', $recipient->ID);
    }

    public function testAddToGroup()
    {
        $response = (object) ['Status' => 1];

        $partialMock = $this->getMockBuilder(HigherLogicApi::class)
            ->setConstructorArgs(['testUser', 'testPass'])
            ->onlyMethods(['callApi'])
            ->getMock();

        $partialMock->method('callApi')
            ->willReturn($response);

        $result = $partialMock->addToGroup('userId', 'groupId');

        $this->assertTrue($result);
    }

    public function testUpsertRecipientAdd()
    {
        $response = (object) ['ID' => 'new_recipient_id'];

        $partialMock = $this->getMockBuilder(HigherLogicApi::class)
            ->setConstructorArgs(['testUser', 'testPass'])
            ->onlyMethods(['callApi', 'getRecipientByEmail'])
            ->getMock();

        $partialMock->method('callApi')
            ->willReturn($response);

        $partialMock->method('getRecipientByEmail')
            ->willReturn(false);

        $result = $partialMock->upsertRecipient('new@example.com', 'First', 'Last', '12345');

        $this->assertEquals('new_recipient_id', $result);
    }

    public function testUpsertRecipientUpdate()
    {
        $recipient = (object) ['ID' => 'existing_recipient_id'];

        $partialMock = $this->getMockBuilder(HigherLogicApi::class)
            ->setConstructorArgs(['testUser', 'testPass'])
            ->onlyMethods(['callApi', 'getRecipientByEmail'])
            ->getMock();

        $partialMock->method('getRecipientByEmail')
            ->willReturn($recipient);

        $response = (object) ['ID' => 'existing_recipient_id'];

        $partialMock->method('callApi')
            ->willReturn($response);

        $result = $partialMock->upsertRecipient('existing@example.com', 'First', 'Last', '12345');

        $this->assertEquals('existing_recipient_id', $result);
    }
}
