<?php

namespace FMASites\HigherLogicApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class HigherLogicApi
{
    private $url = 'https://dna.magnetmail.net/ApiAdapter/Rest/';
    private $loginId;
    private $sessionId;
    private $userId;
    public $apiStatus = 1;
    private $client;
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
        $this->client = new Client([
            'base_uri' => $this->url,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);

        try {
            $result = $this->callApi('Authenticate', [
                'Password' => $this->password,
                'UserName' => $this->username,
            ]);
            $this->loginId = $result->LoginID;
            $this->sessionId = $result->SessionID;
            $this->userId = $result->UserID;
        } catch (\Exception $e) {
            Log::error($e);
            $this->apiStatus = 0;
        }
    }

    protected function callApi($uri, $content = [])
    {
        if ($this->apiStatus) {
            if (isset($this->sessionId) && isset($this->userId)) {
                $content['SessionID'] = $this->sessionId;
                $content['UserID'] = $this->userId;
            }

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

    public function getRecipientByEmail($email)
    {
        try {
            $recipient = $this->callApi('SearchRecipient', ['Email' => $email]);
            if (count($recipient)) {
                return $recipient[0];
            }
        } catch (\Exception $e) {
            Log::error($e);
        }
        return false;
    }

    public function addToGroup($userId, $groupId)
    {
        try {
            $res = $this->callApi('EditRecipientGroups', [
                'ID' => $userId,
                'NewGroups' => [(int) $groupId],
                'UnsubscribeGroups' => [],
            ]);

            if ($res->Status == 1) {
                return true;
            }
        } catch (\Exception $e) {
            Log::error($e);
        }
        return false;
    }

    public function upsertRecipient($email, $firstName = null, $lastName = null, $zip = null, $company = null, $address = null, $city = null, $state = null, $phone = null)
    {
        $recipientDetails = new \stdClass();
        $recipientDetails->Email = $email;

        $recipient = $this->getRecipientByEmail($email);
        $upsertType = 'add';
        if ($recipient) {
            $upsertType = 'update';
            $recipientDetails->ID = $recipient->ID;
        }

        if (isset($address)) {
            $recipientDetails->Address = $address;
        }
        if (isset($city)) {
            $recipientDetails->City = $city;
        }
        if (isset($company)) {
            $recipientDetails->Company = $company;
        }
        if (isset($firstName)) {
            $recipientDetails->FirstName = $firstName;
        }
        if (isset($lastName)) {
            $recipientDetails->LastName = $lastName;
        }
        if (isset($phone)) {
            $recipientDetails->Phone = $phone;
        }
        /*
        if (isset($state)) {
            $recipientDetails->State = 'Other';
            if (array_key_exists($state, Abbreviations::$states)) {
                $recipientDetails->State = Abbreviations::$states[$state];
            }
        }
        */
        if (isset($zip)) {
            $recipientDetails->Zip = $zip;
        }

        $fields = [
            'RecipientDetails' => $recipientDetails,
            'UpdateWithNullifNotPassed' => false,
            'UpsertType' => $upsertType,
            'ValidateEmail' => false,
        ];
        try {
            $res = $this->callApi('UpsertRecipient', $fields);
            return $res->ID;
        } catch (\Exception $e) {
            Log::error($e);
        }
        return false;
    }
}
