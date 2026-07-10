<?php

namespace App\Controller;

use App\Repository\MissionRepository;
use App\Repository\CriteriaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MissionController extends AbstractController
{
    #[Route('/mission', name: 'mission')]
    public function index(
        Request $request,
        MissionRepository $missionRepo,
        CriteriaRepository $criteriaRepo
    ): Response {

        $session = $request->getSession();
        $connectedSubId = (string) $session->get('connected_sub_id', '');

        if ($connectedSubId === '') {
            return $this->redirectToRoute('home');
        }

        $isArbitre = str_starts_with($connectedSubId, 'Arbitre');

        $missionId = null;
        $criteria = [];
        $criteriaDescriptions = [];

        foreach ($criteriaRepo->findAll() as $criterion) {
            $fields = $criterion['fields'] ?? [];
            $id = $fields['id']['stringValue'] ?? basename($criterion['name'] ?? '');
            $description = $fields['description']['stringValue'] ?? $id;

            if ($id !== '') {
                $criteriaDescriptions[$id] = $description;
            }
        }

        if ($request->isMethod('POST') && $request->request->getBoolean('generate_mission') && !$isArbitre) {
            $missionId = $missionRepo->generateMission($connectedSubId);
            $criteria = $missionRepo->getMissionCriteria($missionId);
            $missionPayload = [
                'missionId' => $missionId,
                'criteria' => $criteria,
            ];

            // Persist the last mission in the session so a refresh still shows it.
            $session->set('last_generated_mission', $missionPayload);

            $session->getFlashBag()->add('generated_mission', $missionPayload);

            return $this->redirectToRoute('mission');
        }

        $generatedMission = $session->getFlashBag()->get('generated_mission', []);

        if ($generatedMission !== []) {
            $missionId = $generatedMission[0]['missionId'] ?? null;
            $criteria = $generatedMission[0]['criteria'] ?? [];
        } else {
            $savedMission = $session->get('last_generated_mission', []);

            if (is_array($savedMission) && $savedMission !== []) {
                $missionId = $savedMission['missionId'] ?? null;
                $criteria = $savedMission['criteria'] ?? [];
            }
        }

        return $this->render('mission/index.html.twig', [
            'missionId' => $missionId,
            'connectedSubId' => $connectedSubId,
            'isArbitre' => $isArbitre,
            'criteria' => $criteria,
            'criteriaDescriptions' => $criteriaDescriptions,
        ]);
    }
}
