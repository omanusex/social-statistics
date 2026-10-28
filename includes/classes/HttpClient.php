<?php

/**
 * Minimal HTTP GET using cURL, shared by the API clients.
 */
class HttpClient
{
    public static function get(string $url, int $timeout = 30): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => 'SocialStatsObservatory/1.0 (student project)',
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Connection failed: ' . $error);
        }
        if ($status >= 400) {
            throw new RuntimeException("Server answered with HTTP $status");
        }
        return $body;
    }
}
