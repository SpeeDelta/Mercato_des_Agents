<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClassementController extends AbstractController
{
    #[Route('/classement', name: 'app_classement')]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $connectedSubId = (string) $request->getSession()->get('connected_sub_id', '');
        $isArbitre = str_starts_with($connectedSubId, 'Arbitre');
        $search = trim((string) $request->query->get('q', ''));

        if ($request->isMethod('POST') && $request->request->getBoolean('add_point')) {
            if ($isArbitre) {
                $targetSubId = trim((string) $request->request->get('subId'));

                if ($targetSubId !== '') {
                    $userRepository->incrementScore($targetSubId);
                    $this->addFlash('success', sprintf('1 point ajouté à %s.', $targetSubId));
                } else {
                    $this->addFlash('danger', 'Impossible d\'ajouter un point sans subId.');
                }
            } else {
                $this->addFlash('danger', 'Seul un arbitre peut ajouter des points.');
            }

            return $this->redirectToRoute('app_classement', $search !== '' ? ['q' => $search] : []);
        }

        $ranking = [];

        foreach ($userRepository->findAll() as $userDoc) {
            $fields = $userDoc['fields'] ?? [];
            $subId = $fields['subId']['stringValue'] ?? basename($userDoc['name'] ?? '');

            if (str_starts_with($subId, 'Arbitre')) {
                continue;
            }

            $pseudo = trim((string) ($fields['pseudo']['stringValue'] ?? ''));
            $score = (int) ($fields['score']['integerValue'] ?? 0);

            if ($search !== '') {
                $needle = mb_strtolower($search);
                $haystackSubId = mb_strtolower($subId);
                $haystackPseudo = mb_strtolower($pseudo);

                if (!str_contains($haystackSubId, $needle) && !str_contains($haystackPseudo, $needle)) {
                    continue;
                }
            }

            $ranking[] = [
                'subId' => $subId,
                'pseudo' => $pseudo !== '' ? $pseudo : $subId,
                'score' => $score,
            ];
        }

        usort($ranking, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });

        return $this->render('classement/index.html.twig', [
            'ranking' => $ranking,
            'search' => $search,
            'isArbitre' => $isArbitre,
        ]);
    }
}
