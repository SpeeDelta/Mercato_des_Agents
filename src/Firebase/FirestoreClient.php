<?php
// src/Firebase/FirestoreClient.php
namespace App\Firebase;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class FirestoreClient
{
    private string $projectId;
    private HttpClientInterface $http;

    public function __construct(HttpClientInterface $http, string $projectId)
    {
        $this->http = $http;
        $this->projectId = $projectId;
    }

    private function baseUrl(string $collection): string
    {
        return sprintf(
            'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents/%s',
            $this->projectId,
            $collection
        );
    }

    public function list(string $collection): array
    {
        $response = $this->http->request('GET', $this->baseUrl($collection));
        return $response->toArray(false);
    }

    public function get(string $collection, string $id): array
    {
        $url = $this->baseUrl($collection).'/'.$id;
        $response = $this->http->request('GET', $url);
        return $response->toArray(false);
    }

    public function set(string $collection, string $id, array $fields): array
    {
        $url = $this->baseUrl($collection).'/'.$id;
        $body = ['fields' => $fields];

        $response = $this->http->request('PATCH', $url, [
            'json' => $body,
        ]);

        return $response->toArray(false);
    }

    public function create(string $collection, array $fields): array
    {
        $response = $this->http->request('POST', $this->baseUrl($collection), [
            'json' => ['fields' => $fields],
        ]);

        return $response->toArray(false);
    }
}
?>
