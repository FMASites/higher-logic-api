<?php

namespace FMASites\HigherLogicApi\Tests;

use FMASites\HigherLogicApi\RealMagnet;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RealMagnetTest extends TestCase
{
    private string $authPassword = 'test-password';
    private string $authUsername = 'test-username';
    private array $defaultAuthResponseBody = [
        'LoginID' => 123456789,
        'SessionID' => 'test-session-id',
        'UserID' => 'test-user-id'];
    private MockHandler $mockHttpHandler;
    private array $httpHistory;
    private Client $mockHttpClient;

    function setUp(): void
    {
        parent::setUp();

        // Reset history
        $this->httpHistory = [];

        // By default, the first HTTP call is assumed to be a successful Authenticate
        $this->mockHttpHandler = new MockHandler([
            new Response(200, [], json_encode($this->defaultAuthResponseBody))
        ]);
        $handlerStack = HandlerStack::create($this->mockHttpHandler);

        $historyMiddleware = Middleware::history($this->httpHistory);
        $handlerStack->push($historyMiddleware);
        $this->mockHttpClient = new Client(['handler' => $handlerStack]);
    }

    #[Test]
    public function constructor_authenticatesHigherLogicUser()
    {
        // Act
        $api = $this->getApi();
        $params = json_decode($this->httpHistory[0]['request']->getBody()->getContents());

        // Assert
        $this->assertCount(1, $this->httpHistory);
        $this->assertEquals($this->authUsername, $params->UserName);
        $this->assertEquals($this->authPassword, $params->Password);
    }

    #[Test]
    public function authenticateUser_savesAuthResponseValues_whenAuthPasses()
    {
        // Act
        $api = $this->getApi();

        // Assert
        $this->assertEquals($this->defaultAuthResponseBody['LoginID'], $api->loginId);
        $this->assertEquals($this->defaultAuthResponseBody['SessionID'], $api->sessionId);
        $this->assertEquals($this->defaultAuthResponseBody['UserID'], $api->userId);
    }

    #[Test]
    public function authenticateUser_setsApiStatus_whenApiCallFails()
    {
        // Arrange
        $this->mockHttpHandler->reset();
        $this->mockHttpHandler->append(new Response(500, [], json_encode('')));

        // Act
        $api = $this->getApi();

        // Assert
        $this->assertEquals(0, $api->apiStatus);
    }

    #[Test]
    public function authenticateUser_setsApiStatus_whenAuthFails()
    {
        // Arrange
        $this->mockHttpHandler->reset();
        $this->mockHttpHandler->append(new Response(200, [], json_encode([
            // This is what a RealMagnet failed auth attempt payload looks like
            'LoginID' => 0,
            'SessionID' => null,
            'UserID' => null
        ])));

        // Act
        $api = $this->getApi();

        // Assert
        $this->assertEquals(0, $api->apiStatus);
    }

    #[Test]
    public function getRecipientByEmail_false_whenNotFound()
    {
        // Arrange
        $this->mockHttpHandler->append(new Response(200, [], json_encode([])));
        $api = $this->getApi();

        // Act
        $result = $api->getRecipientByEmail('bogus@notachance.org');

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function getRecipientByEmail_makesCorrectCall()
    {
        // Arrange
        $testEmail = 'chuck.norris@roundhouse.com';
        $this->mockHttpHandler->append(new Response(200, [], ''));
        $api = $this->getApi();

        // Act
        $api->getRecipientByEmail($testEmail);
        $requestInfo = $this->getLastHttpRequestDetails();

        // Assert
        // Boilerplate
        $this->assertEquals('POST', $requestInfo['method']);
        $this->assertEquals($this->defaultAuthResponseBody['UserID'], $requestInfo['data']->UserID);
        $this->assertEquals($this->defaultAuthResponseBody['SessionID'], $requestInfo['data']->SessionID);
        // Specific
        $this->assertEquals('SearchRecipient', $requestInfo['uri']);
        $this->assertEquals($testEmail, $requestInfo['data']->Email);
    }

    #[Test]
    public function getRecipientByEmail_recipientInfo_whenRecipientFound()
    {
        // Arrange
        $knownEmail = 'youknow@thisguy.com';
        $this->mockHttpHandler->append(new Response(200, [], json_encode([
            ['Email' => $knownEmail]
        ])));
        $api = $this->getApi();

        // Act
        $response = $api->getRecipientByEmail($knownEmail);

        // Assert
        // The payload can vary quite a bit and isn't the focus of this test, so just be sure the response exists
        $this->assertIsObject($response);
    }

    #[Test]
    public function getRecipientByEmail_false_whenRecipientNotFound()
    {
        // Arrange
        $unknownEmail = 'whoisthis@unknownemail.com';
        $this->mockHttpHandler->append(new Response(200, [], json_encode([])));
        $api = $this->getApi();

        // Act
        $response = $api->getRecipientByEmail($unknownEmail);

        // Assert
        $this->assertFalse($response);
    }

    #[Test]
    public function addToGroup_makesCorrectCall()
    {
        // Arrange
        $userId = 11111;
        $groupId = 22222;
        $this->mockHttpHandler->append(new Response(200, [], ''));
        $api = $this->getApi();

        // Act
        $api->addToGroup($userId, $groupId);
        $requestInfo = $this->getLastHttpRequestDetails();

        // Assert
        // Boilerplate
        $this->assertEquals('POST', $requestInfo['method']);
        $this->assertEquals($this->defaultAuthResponseBody['UserID'], $requestInfo['data']->UserID);
        $this->assertEquals($this->defaultAuthResponseBody['SessionID'], $requestInfo['data']->SessionID);
        // Specific
        $this->assertEquals('EditRecipientGroups', $requestInfo['uri']);
        $this->assertEquals($userId, $requestInfo['data']->ID);
        $this->assertEquals([$groupId], $requestInfo['data']->NewGroups);
        $this->assertEquals([], $requestInfo['data']->UnsubscribeGroups);
    }

    #[Test]
    public function addToGroup_true_responseStatusIsOne()
    {
        // Arrange
        $userId = 11111;
        $groupId = 22222;
        $this->mockHttpHandler->append(new Response(200, [], json_encode(['Status' => 1])));
        $api = $this->getApi();

        // Act
        $response = $api->addToGroup($userId, $groupId);

        // Assert
        $this->assertTrue($response);
    }

    #[Test]
    public function addToGroup_false_responseStatusNotOne()
    {
        // Arrange
        $userId = 11111;
        $groupId = 22222;
        $this->mockHttpHandler->append(new Response(200, [], json_encode(['Status' => 50])));
        $api = $this->getApi();

        // Act
        $response = $api->addToGroup($userId, $groupId);

        // Assert
        $this->assertFalse($response);
    }

    #[Test]
    public function upsertRecipient_makesCorrectCall_whenRecipientIsNew()
    {
        // Arrange
        $recipientInfo = $this->getRecipientInfoObject();
        // For getting the user's type: recipient not found
        $this->mockHttpHandler->append(new Response(200, [], json_encode([])));
        // For sending the upsert the API
        $this->mockHttpHandler->append(new Response(200, [], ''));
        $api = $this->getApi();

        // Act
        $api->upsertRecipient($recipientInfo->Email, $recipientInfo->FirstName, $recipientInfo->LastName, $recipientInfo->Zip, $recipientInfo->Company, $recipientInfo->Address, $recipientInfo->City, $recipientInfo->State, $recipientInfo->Phone);
        $requestInfo = $this->getLastHttpRequestDetails();

        // Assert
        // Boilerplate
        $this->assertEquals('POST', $requestInfo['method']);
        $this->assertEquals($this->defaultAuthResponseBody['UserID'], $requestInfo['data']->UserID);
        $this->assertEquals($this->defaultAuthResponseBody['SessionID'], $requestInfo['data']->SessionID);
        // Specific
        $this->assertEquals('UpsertRecipient', $requestInfo['uri']);
        $this->assertEquals($recipientInfo, $requestInfo['data']->RecipientDetails);
        $this->assertEquals('add', $requestInfo['data']->UpsertType);
        $this->assertFalse($requestInfo['data']->ValidateEmail);
    }

    #[Test]
    public function upsertRecipient_makesCorrectCall_whenRecipientExists()
    {
        // Arrange
        $recipientInfo = $this->getRecipientInfoObject();
        // For getting the user's type: recipient not found
        $this->mockHttpHandler->append(new Response(200, [], json_encode([
            ['ID' => 123]
        ])));
        // For sending the upsert the API
        $this->mockHttpHandler->append(new Response(200, [], ''));
        $api = $this->getApi();

        // Act
        $api->upsertRecipient($recipientInfo->Email, $recipientInfo->FirstName, $recipientInfo->LastName, $recipientInfo->Zip, $recipientInfo->Company, $recipientInfo->Address, $recipientInfo->City, $recipientInfo->State, $recipientInfo->Phone);
        $requestInfo = $this->getLastHttpRequestDetails();

        // Assert
        // Boilerplate
        $this->assertEquals('POST', $requestInfo['method']);
        $this->assertEquals($this->defaultAuthResponseBody['UserID'], $requestInfo['data']->UserID);
        $this->assertEquals($this->defaultAuthResponseBody['SessionID'], $requestInfo['data']->SessionID);
        // Specific
        $this->assertEquals('UpsertRecipient', $requestInfo['uri']);
        // ID gets added when recipient is found
        $this->assertEquals(123, $requestInfo['data']->RecipientDetails->ID);
        $this->assertEquals('update', $requestInfo['data']->UpsertType);
        $this->assertFalse($requestInfo['data']->ValidateEmail);
    }

    // **************************************************************
    // Helper functions
    // **************************************************************

    /***
     * Gets an instance of the RealMagnet API to test. This is done as a "get" helper instead of being
     * set as a class property in the setup (i.e. $this->api) to allow alterations in the HTTP client
     * to be made before instantiating the API.
     * @return RealMagnet
     */
    private function getApi()
    {
        return new RealMagnet(
            $this->mockHttpClient,
            'test-username',
            'test-password');
    }

    private function getLastHttpRequestDetails()
    {
        if (count($this->httpHistory)) {
            $details = [];
            $lastCall = $this->httpHistory[count($this->httpHistory) - 1]['request'];
            $details['uri'] = $lastCall->getUri()->getPath();
            $details['method'] = $lastCall->getMethod();
            $details['data'] = json_decode($lastCall->getBody()->getContents());

            return $details;
        }
        return null;
    }

    private function getRecipientInfoObject()
    {
        return (object)[
            'Email' => 'test-upsert@example.com',
            'FirstName' => "Chuck",
            'LastName' => "Norris",
            'Zip' => '12345',
            'Company' => 'ACME Inc.',
            'Address' => '123 Test Dr.',
            'City' => 'Testville',
            'State' => 'Confusion',
            'Phone' => '4445556666'
        ];
    }
}
