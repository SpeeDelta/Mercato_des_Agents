<?php
// src/Firebase/FirestoreClient.php
namespace App\Firebase;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class FirestoreClient
{
    private string $projectId;
    private string $apiKey;
    private string $database;
    private HttpClientInterface $http;

    public function __construct(HttpClientInterface $http, string $projectId, string $apiKey, string $database = 'default')
    {
        $this->http = $http;
        $this->projectId = $projectId;
        $this->apiKey = $apiKey;
        $this->database = $database;
    }

    private function baseUrl(string $collection): string
    {
        return sprintf(
            'https://firestore.googleapis.com/v1/projects/%s/databases/%s/documents/%s',
            $this->projectId,
            $this->database,
            $collection
        );
    }

    private function requestOptions(): array
    {
        return [
            'query' => [
                'key' => $this->apiKey,
            ],
        ];
    }

    public function list(string $collection): array
    {
        $response = $this->http->request('GET', $this->baseUrl($collection), $this->requestOptions());
        return $response->toArray(false);
    }

    public function get(string $collection, string $id): array
    {
        $url = $this->baseUrl($collection).'/'.$id;
        $response = $this->http->request('GET', $url, $this->requestOptions());
        return $response->toArray(false);
    }

    /**
     * Set/update a document.
     * If $mergeFields is provided, only those fields are updated (merge mode).
     * If $mergeFields is null, the entire document is replaced.
     */
    public function set(string $collection, string $id, array $fields, ?array $mergeFields = null): array
    {
        $url = $this->baseUrl($collection).'/'.$id;
        $body = ['fields' => $fields];

        $options = $this->requestOptions();

        // If mergeFields is provided, add updateMask to only update those fields
        if ($mergeFields !== null) {
            $options['query']['updateMask.fieldPaths'] = implode(',', $mergeFields);
        }

        $response = $this->http->request('PATCH', $url, [
            ...$options,
            'json' => $body,
        ]);

        return $response->toArray(false);
    }

    public function create(string $collection, array $fields): array
    {
        $response = $this->http->request('POST', $this->baseUrl($collection), [
            ...$this->requestOptions(),
            'json' => ['fields' => $fields],
        ]);

        return $response->toArray(false);
    }

    public function delete(string $collection, string $id): void
    {
        $url = $this->baseUrl($collection).'/'.$id;
        $this->http->request('DELETE', $url, $this->requestOptions());
    }
}
?>
