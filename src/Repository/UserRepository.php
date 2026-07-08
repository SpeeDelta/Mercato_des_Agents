<?php

namespace App\Repository;

use App\Firebase\FirestoreClient;

class UserRepository
{
    private FirestoreClient $firestore;

    public function __construct(FirestoreClient $firestore)
    {
        $this->firestore = $firestore;
    }

    public function findAll(): array
    {
        $response = $this->firestore->list('users');

        return $response['documents'] ?? [];
    }

    /**
     * Vérifie la connexion Firestore et renvoie le payload pour debug.
     * Retourne un tableau ['ok' => bool, 'payload' => array]
     */
    public function checkConnection(): array
    {
        try {
            $response = $this->firestore->list('users');
        } catch (\Throwable $e) {
            return ['ok' => false, 'payload' => ['exception' => $e->getMessage()]];
        }

        $ok = isset($response['documents']) && is_array($response['documents']);

        return ['ok' => $ok, 'payload' => $response];
    }

    /**
     * Vérifie si un user existe par son subId.
     */
    public function exists(string $subId): bool
    {
        return $this->findDocumentIdBySubId($subId) !== null;
    }

    /**
     * Change le pseudo d'un user.
     * If user does not exist, creates it with the given subId and pseudo.
     * Only updates the pseudo field, preserving other fields (score, isActive, etc.)
     */
    public function updatePseudo(string $subId, string $newPseudo): void
    {
        $docId = $this->findDocumentIdBySubId($subId);

        // If user does not exist, create it
        if ($docId === null) {
            $this->createUser($subId, $newPseudo);
            return;
        }

        $fields = [
            'pseudo' => ['stringValue' => $newPseudo],
        ];

        // Use merge mode: only update the 'pseudo' field, preserve other fields (score, isActive, etc.)
        $this->firestore->set('users', $docId, $fields, ['pseudo']);
    }

    /**
     * Creates a new user with the given subId and pseudo.
     */
    public function createUser(string $subId, string $pseudo = ''): array
    {
        if (empty($subId)) {
            throw new \Exception('SubId cannot be empty');
        }

        $fields = [
            'subId' => ['stringValue' => $subId],
            'pseudo' => ['stringValue' => $pseudo],
            'score' => ['integerValue' => '0'],
            'isActive' => ['booleanValue' => true],
        ];

        return $this->firestore->create('users', $fields);
    }

    /**
     * Find the Firestore document id for a user by their subId field.
     * If multiple documents have the same subId, returns the first one.
     * This ensures we work with a unique Firestore document ID.
     */
    private function findDocumentIdBySubId(string $subId): ?string
    {
        $response = $this->firestore->list('users');
        $docs = $response['documents'] ?? [];

        foreach ($docs as $doc) {
            $fields = $doc['fields'] ?? [];
            $docSubId = $fields['subId']['stringValue'] ?? null;
            // Match subId (case-sensitive) and ensure subId is not empty/null
            if ($docSubId === $subId && !empty($subId)) {
                return basename($doc['name']);
            }
        }

        return null;
    }

    /**
     * Augmente le score d'un user de 1
     * Only updates the score field, preserves other fields (pseudo, isActive, etc.)
     */
    public function incrementScore(string $subId): void
    {
        $docId = $this->findDocumentIdBySubId($subId);

        if (!$docId) {
            throw new \Exception("User $subId not found");
        }

        $user = $this->firestore->get('users', $docId);

        $currentScore = (int)($user['fields']['score']['integerValue'] ?? 0);

        $fields = [
            'score' => ['integerValue' => $currentScore + 1],
        ];

        // Use merge mode: only update the 'score' field, preserve other fields
        $this->firestore->set('users', $docId, $fields, ['score']);
    }
}
