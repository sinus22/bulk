<?php

namespace App\Http;

use Exception;

class Telegram
{
    private string $apiEndpoint;

    public function __construct(?string $apiEndpoint = null)
    {
        $this->apiEndpoint = $apiEndpoint ?? 'https://api.telegram.org/bot';
    }

    private function httpApiCall(string $token, string $method, array $params = []): array
    {
        $ch = curl_init($this->apiEndpoint . $token . '/' . $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if (!empty($params)) {
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($params)
            ]);
        }

        $response = curl_exec($ch);

        if ($response === false) {
            throw new Exception(curl_error($ch));
        }

        $json_response = json_decode($response, flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        curl_close($ch);

        return $json_response;
    }

    public function getMe(string $token)
    {
        return $this->httpApiCall($token, 'getMe');
    }

    public function sendMessage(string $token, int $chat_id, array $data)
    {
        $data['chat_id'] = $chat_id;
        return $this->httpApiCall($token, 'sendMessage', $data);
    }
}