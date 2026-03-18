<?php

class SupabaseService
{
    private $baseUrl;
    private $serviceRoleKey;
    private $table;

    public function __construct()
    {
        $this->baseUrl = defined('SUPABASE_URL') ? SUPABASE_URL : '';
        $this->serviceRoleKey = defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : '';
        $this->table = defined('SUPABASE_TABLE') ? SUPABASE_TABLE : 'news';
    }

    public function isConfigured()
    {
        return defined('SUPABASE_ENABLED')
            && SUPABASE_ENABLED
            && $this->baseUrl !== ''
            && $this->serviceRoleKey !== ''
            && $this->table !== '';
    }

    public function fetchNews($limit = MAX_NEWS_ITEMS)
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $query = http_build_query(array(
            'select' => 'guid,title,summary,link,source,published_at,image,created_at,updated_at',
            'order' => 'published_at.desc',
            'limit' => max(1, (int) $limit),
        ), '', '&', PHP_QUERY_RFC3986);

        $response = $this->request('GET', $this->buildTableUrl() . '?' . $query);

        if (!isset($response['success']) || $response['success'] !== true || !isset($response['data']) || !is_array($response['data'])) {
            return null;
        }

        return array_map(array($this, 'mapNewsRecord'), $response['data']);
    }

    public function upsertNews(array $news)
    {
        if (!$this->isConfigured()) {
            return false;
        }

        if (count($news) === 0) {
            return true;
        }

        $payload = array();

        foreach ($news as $item) {
            if (!is_array($item)) {
                continue;
            }

            $payload[] = array(
                'guid' => isset($item['guid']) ? (string) $item['guid'] : '',
                'title' => isset($item['title']) ? (string) $item['title'] : '',
                'summary' => isset($item['summary']) ? (string) $item['summary'] : '',
                'link' => isset($item['link']) ? (string) $item['link'] : '',
                'source' => isset($item['source']) ? (string) $item['source'] : 'ABI',
                'published_at' => isset($item['published_at']) ? (string) $item['published_at'] : '',
                'image' => isset($item['image']) && trim((string) $item['image']) !== '' ? (string) $item['image'] : null,
            );
        }

        if (count($payload) === 0) {
            return true;
        }

        $query = http_build_query(array(
            'on_conflict' => 'guid',
        ), '', '&', PHP_QUERY_RFC3986);

        $response = $this->request(
            'POST',
            $this->buildTableUrl() . '?' . $query,
            $payload,
            array('Prefer: resolution=merge-duplicates,return=minimal')
        );

        return isset($response['success']) && $response['success'] === true;
    }

    public function fetchLatestUpdatedAt()
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $query = http_build_query(array(
            'select' => 'updated_at',
            'order' => 'updated_at.desc',
            'limit' => 1,
        ), '', '&', PHP_QUERY_RFC3986);

        $response = $this->request('GET', $this->buildTableUrl() . '?' . $query);

        if (!isset($response['success']) || $response['success'] !== true || !isset($response['data'][0]['updated_at'])) {
            return null;
        }

        $updatedAt = trim((string) $response['data'][0]['updated_at']);

        return $updatedAt !== '' ? $updatedAt : null;
    }

    private function buildTableUrl()
    {
        return $this->baseUrl . '/rest/v1/' . rawurlencode($this->table);
    }

    private function mapNewsRecord(array $record)
    {
        return array(
            'guid' => isset($record['guid']) ? (string) $record['guid'] : '',
            'title' => isset($record['title']) ? (string) $record['title'] : '',
            'summary' => isset($record['summary']) ? (string) $record['summary'] : '',
            'link' => isset($record['link']) ? (string) $record['link'] : '',
            'source' => isset($record['source']) ? (string) $record['source'] : 'ABI',
            'published_at' => isset($record['published_at']) ? (string) $record['published_at'] : '',
            'image' => isset($record['image']) ? (string) $record['image'] : '',
        );
    }

    private function request($method, $url, $payload = null, array $extraHeaders = array())
    {
        $headers = array_merge(array(
            'apikey: ' . $this->serviceRoleKey,
            'Authorization: Bearer ' . $this->serviceRoleKey,
            'Accept: application/json',
        ), $extraHeaders);

        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (!is_string($payload)) {
                return array('success' => false, 'data' => null);
            }
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);

            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
            ));

            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }

            $rawBody = curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError !== '') {
                return array('success' => false, 'data' => null);
            }

            return array(
                'success' => $statusCode >= 200 && $statusCode < 300,
                'data' => $this->decodeResponseBody($rawBody),
            );
        }

        $contextOptions = array(
            'http' => array(
                'method' => $method,
                'ignore_errors' => true,
                'timeout' => 20,
                'header' => implode("\r\n", $headers),
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        );

        if ($payload !== null) {
            $contextOptions['http']['content'] = $payload;
        }

        $context = stream_context_create($contextOptions);
        $rawBody = @file_get_contents($url, false, $context);
        $statusCode = $this->extractHttpStatusCode(isset($http_response_header) ? $http_response_header : array());

        return array(
            'success' => $statusCode >= 200 && $statusCode < 300,
            'data' => $this->decodeResponseBody($rawBody),
        );
    }

    private function decodeResponseBody($rawBody)
    {
        if (!is_string($rawBody) || trim($rawBody) === '') {
            return null;
        }

        $decoded = json_decode($rawBody, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $rawBody;
    }

    private function extractHttpStatusCode(array $headers)
    {
        if (!isset($headers[0])) {
            return 0;
        }

        if (preg_match('/\s(\d{3})\s/', (string) $headers[0], $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }
}
