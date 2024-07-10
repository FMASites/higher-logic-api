<?php

namespace FMASites\HigherLogicApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class HigherLogicApi
{
    private $url = 'https://dna.magnetmail.net/ApiAdapter/Rest/';
    public $loginId;
    public $sessionId;
    public $userId;
    public $apiStatus = 1;
    public $client;
    private $userName;
    private $password;

    public static $userFields = [
        'Address',
        'City',
        'Email',
        'FirstName',
        'LastName',
        'Phone',
        'State',
        'Zip',
    ];

    public function __construct($userName, $password)
    {
        $this->userName = $userName;
        $this->password = $password;
        $this->initializeClient();
        $this->authenticateUser();
    }

    private function initializeClient()
    {
        $this->client = new Client([
            'base_uri' => $this->url,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function authenticateUser()
    {
        try {
            $result = $this->callApi('Authenticate', [
                'Password' => $this->password,
                'UserName' => $this->userName,
            ]);
            $this->loginId = $result->LoginID;
            $this->sessionId = $result->SessionID;
            $this->userId = $result->UserID;
        } catch (\Exception $e) {
            $this->apiStatus = 0;
        }
    }

    protected function callApi($uri, $content = [])
    {
        if ($this->apiStatus) {
            $content = $this->addSessionInfo($content);

            try {
                $response = $this->client->post($uri . '/', [
                    'json' => $content,
                ]);

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
            'NewGroups' => [(int) $groupId],
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
