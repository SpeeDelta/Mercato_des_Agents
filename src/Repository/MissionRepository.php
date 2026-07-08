<?php

namespace App\Repository;

use App\Firebase\FirestoreClient;
use App\Repository\UserRepository;

class MissionRepository
{
    private FirestoreClient $firestore;
    private CriteriaRepository $criteriaRepo;
    private UserRepository $userRepo;

    public function __construct(FirestoreClient $firestore, CriteriaRepository $criteriaRepo, UserRepository $userRepo)
    {
        $this->firestore = $firestore;
        $this->criteriaRepo = $criteriaRepo;
        $this->userRepo = $userRepo;
    }

    /**
     * Vérifie si un user a déjà une mission non validée
     */
    public function getActiveMission(string $userSubId): ?string
    {
        $response = $this->firestore->list('missions');
        $missions = $response['documents'] ?? [];

        foreach ($missions as $mission) {
            $fields = $mission['fields'];
            $missionUser = $fields['userSubId']['stringValue'];
            $validated = $fields['validated']['booleanValue'];

            if ($missionUser === $userSubId && $validated === false) {
                return basename($mission['name']); // ex: "M-1720283920"
            }
        }

        return null;
    }

    /**
     * Génère une mission (1 user + 3 critères)
     */
    public function generateMission(string $subId): string
    {
        $this->deleteUserMissions($subId);

        $missionId = 'M-' . str_replace('.', '', (string) microtime(true));

        // Créer la mission
        $this->firestore->set('missions', $missionId, [
            'userSubId' => ['stringValue' => $subId],
            'validated' => ['booleanValue' => false],
            'createdAt' => ['stringValue' => date('c')],
        ]);

        // Tirer 3 critères
        $criteriaIds = array_slice($this->criteriaRepo->findRandomByCategory(), 0, 3);

        // Créer les MissionCriteria
        foreach ($criteriaIds as $critId) {
            $mcId = $missionId . '_' . $critId;

            $this->firestore->set('missionCriteria', $mcId, [
                'missionId' => ['stringValue' => $missionId],
                'criteriaId' => ['stringValue' => $critId],
            ]);
        }

        return $missionId;
    }

    private function deleteUserMissions(string $subId): void
    {
        $response = $this->firestore->list('missions');
        $missions = $response['documents'] ?? [];

        foreach ($missions as $mission) {
            $fields = $mission['fields'] ?? [];
            $missionUser = $fields['userSubId']['stringValue'] ?? null;

            if ($missionUser === $subId) {
                $missionId = basename($mission['name']);
                $this->deleteMissionAndCriteria($missionId);
            }
        }
    }

    private function deleteMissionAndCriteria(string $missionId): void
    {
        $response = $this->firestore->list('missionCriteria');
        $rows = $response['documents'] ?? [];

        foreach ($rows as $row) {
            $fields = $row['fields'] ?? [];
            $rowMissionId = $fields['missionId']['stringValue'] ?? null;

            if ($rowMissionId === $missionId) {
                $rowId = basename($row['name']);
                $this->firestore->delete('missionCriteria', $rowId);
            }
        }

        $this->firestore->delete('missions', $missionId);
    }

    /**
     * Valide une mission
     */
    public function validateMission(string $missionId): void
    {
        $mission = $this->firestore->get('missions', $missionId);
        $fields = $mission['fields'] ?? [];

        if (!$mission || !isset($fields['validated']['booleanValue'])) {
            throw new \Exception("Mission $missionId not found");
        }

        if (($fields['validated']['booleanValue'] ?? false) === true) {
            return;
        }

        $userSubId = $fields['userSubId']['stringValue'] ?? null;

        $this->firestore->set('missions', $missionId, [
            'validated' => ['booleanValue' => true],
        ], ['validated']);

        if ($userSubId) {
            $this->userRepo->incrementScore($userSubId);
        }
    }

    /**
     * Récupère les critères d'une mission
     */
    public function getMissionCriteria(string $missionId): array
    {
        $response = $this->firestore->list('missionCriteria');
        $rows = $response['documents'] ?? [];

        $result = [];

        foreach ($rows as $row) {
            $fields = $row['fields'];
            if ($fields['missionId']['stringValue'] === $missionId) {
                $result[] = $fields['criteriaId']['stringValue'];
            }
        }

        return $result;
    }
}
