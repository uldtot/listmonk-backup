<?php
$ini = parse_ini_file("../config.ini");// config.ini outside of public_html

class ListmonkAPI {
    private string $baseUrl;
    private string $username;
    private string $password;

    public function __construct(string $baseUrl, string $username, string $password) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
    }

    private function request(string $method, string $endpoint, array $data = []): array {
        $url = $this->baseUrl . '/api/' . ltrim($endpoint, '/');

        $ch = curl_init();

        $headers = [
            'Authorization: Basic ' . base64_encode("{$this->username}:{$this->password}"),
            'Content-Type: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if (in_array(strtoupper($method), ['POST', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);

        return [
            'status' => $httpCode,
            'body' => json_decode($response, true)
        ];
    }

    // Eksempel: Hent alle subscribers
    public function getSubscribers(): array {
        return $this->request('GET', 'subscribers');
    }

    // Eksempel: Tilføj ny subscriber
    public function createSubscriber(array $subscriberData): array {
        return $this->request('POST', 'subscribers', $subscriberData);
    }
}
