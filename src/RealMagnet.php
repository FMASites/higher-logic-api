<?php

namespace FMASites\HigherLogicApi;

use Exception;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

class RealMagnet
{
    private GuzzleHttpClient $client;
    public int $loginId = 0;
    public ?string $sessionId = null;
    public ?string $userId = null;
    public int $apiStatus = 1;

    public function __construct(GuzzleHttpClient $httpClient, $username, $password)
    {
        $this->client = $httpClient;
        $this->authenticateUser($username, $password);
    }

    public function authenticateUser($username, $password)
    {
        try {
            $result = $this->callApi('Authenticate', [
                'Password' => $password,
                'UserName' => $username,
            ]);

            // API call failure and authentication failure looks like this:
            if (is_null($result) || (!$result->LoginID && !$result->SessionID && !$result->UserID)) {
                throw new Exception("Authentication failed for $username");
            }

            $this->loginId = $result->LoginID;
            $this->sessionId = $result->SessionID;
            $this->userId = $result->UserID;

        } catch (Throwable $e) {
            $this->apiStatus = 0;
        }
    }

    protected function callApi($uri, $content = [])
    {
        if ($this->apiStatus) {
            $content = $this->addSessionInfo($content);

            try {
                $response = $this->client->post($uri, ['json' => $content]);

                if ($response->getStatusCode() === 200) {
                    return json_decode($response->getBody()->getContents());
                }
            } catch (RequestException $e) {
                Log::error($e);
            }
        }

        return null;
    }

    private function addSessionInfo($content)
    {
        if (isset($this->sessionId) && isset($this->userId)) {
            $content['SessionID'] = $this->sessionId;
            $content['UserID'] = $this->userId;
        }
        return $content;
    }

    public function getRecipientByEmail($email)
    {
        $recipient = $this->callApi('SearchRecipient', ['Email' => $email]);

        if (is_array($recipient) && count($recipient) > 0) {
            return $recipient[0];
        }

        return false;
    }

    public function addToGroup($userId, $groupId)
    {
        $res = $this->callApi('EditRecipientGroups', [
            'ID' => $userId,
            'NewGroups' => [(int)$groupId],
            'UnsubscribeGroups' => [],
        ]);

        return $res && $res->Status == 1;
    }

    public function upsertRecipient($email, $firstName = null, $lastName = null, $zip = null, $company = null, $address = null, $city = null, $state = null, $phone = null)
    {
        $recipientDetails = $this->buildRecipientDetails($email, $firstName, $lastName, $zip, $company, $address, $city, $state, $phone);
        $upsertType = $this->getUpsertType($email, $recipientDetails);

        $fields = [
            'RecipientDetails' => $recipientDetails,
            'UpdateWithNullifNotPassed' => false,
            'UpsertType' => $upsertType,
            'ValidateEmail' => false,
        ];

        $res = $this->callApi('UpsertRecipient', $fields);
        return $res ? $res->ID : false;
    }

    private function buildRecipientDetails($email, $firstName, $lastName, $zip, $company, $address, $city, $state, $phone)
    {
        $recipientDetails = new \stdClass();
        $recipientDetails->Email = $email;
        $recipientDetails->FirstName = $firstName;
        $recipientDetails->LastName = $lastName;
        $recipientDetails->Zip = $zip;
        $recipientDetails->Company = $company;
        $recipientDetails->Address = $address;
        $recipientDetails->City = $city;
        $recipientDetails->State = $state;
        $recipientDetails->Phone = $phone;

        return $recipientDetails;
    }

    private function getUpsertType($email, &$recipientDetails)
    {
        $recipient = $this->getRecipientByEmail($email);
        if ($recipient) {
            $recipientDetails->ID = $recipient->ID;
            return 'update';
        }

        return 'add';
    }
}
