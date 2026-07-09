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
     * Change le pseudo et la ville d'un user.
     * If user does not exist, creates it with the given subId, pseudo and ville.
     * Only updates profile fields, preserving other fields (score, isActive, etc.)
     */
    public function updatePseudo(string $subId, string $newPseudo, string $ville = ''): void
    {
        $docId = $this->findDocumentIdBySubId($subId);

        // If user does not exist, create it
        if ($docId === null) {
            $this->createUser($subId, $newPseudo, $ville);
            return;
        }

        $fields = [
            'pseudo' => ['stringValue' => $newPseudo],
            'ville' => ['stringValue' => $ville],
        ];

        // Use merge mode: only update profile fields, preserve other fields (score, isActive, etc.)
        $this->firestore->set('users', $docId, $fields, ['pseudo', 'ville']);
    }

    /**
     * Creates a new user with the given subId, pseudo and ville.
     */
    public function createUser(string $subId, string $pseudo = '', string $ville = ''): array
    {
        if (empty($subId)) {
            throw new \Exception('SubId cannot be empty');
        }

        $fields = [
            'subId' => ['stringValue' => $subId],
            'pseudo' => ['stringValue' => $pseudo],
            'ville' => ['stringValue' => $ville],
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
     * Only updates the score field, preserves other fields (pseudo, ville, isActive, etc.)
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
