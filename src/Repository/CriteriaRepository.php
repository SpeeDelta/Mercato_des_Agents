<?php

namespace App\Repository;

use App\Firebase\FirestoreClient;

class CriteriaRepository
{
    private FirestoreClient $firestore;

    public function __construct(FirestoreClient $firestore)
    {
        $this->firestore = $firestore;
    }

    /**
     * Récupère tous les critères
     */
    public function findAll(): array
    {
        $response = $this->firestore->list('criteria');

        // Firestore renvoie un tableau avec "documents"
        return $response['documents'] ?? [];
    }

    /**
     * Récupère un critère par ID
     */
    public function find(string $id): ?array
    {
        return $this->firestore->get('criteria', $id);
    }

    /**
     * Tire 3 critères aléatoires
     */
    public function findRandomByCategory(): array
    {
        $all = $this->findAll();

        $byCat = [
            'A' => [],
            'B' => [],
            'C' => [],
            'D' => [],
        ];

        // On trie les critères par catégorie
        foreach ($all as $doc) {
            $fields = $doc['fields'];
            $cat = $fields['categorie']['stringValue'];
            $id = basename($doc['name']);

            if (isset($byCat[$cat])) {
                $byCat[$cat][] = $id;
            }
        }

        // On vérifie qu'il y a au moins 1 critère par catégorie
        foreach (['A','B','C','D'] as $cat) {
            if (count($byCat[$cat]) === 0) {
                throw new \Exception("Pas assez de critères dans la catégorie $cat");
            }
        }

        // On tire 1 critère par catégorie
        return [
            $byCat['A'][array_rand($byCat['A'])],
            $byCat['B'][array_rand($byCat['B'])],
            $byCat['C'][array_rand($byCat['C'])],
            $byCat['D'][array_rand($byCat['D'])],
        ];
    }
}
