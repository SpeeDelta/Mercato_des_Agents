<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(Request $request, UserRepository $userRepo): Response
    {
        $error = null;
        $success = null;
        $dbConnected = false;

        if ($request->isMethod('POST')) {

            $subId = trim($request->request->get('subId'));
            $pseudo = trim($request->request->get('pseudo'));

            if ($subId === '') {
                $error = "Le nom (SubId) est obligatoire.";
            } else {

                // Vérifier si le user existe
                if (!$userRepo->exists($subId)) {
                    $error = "Ce nom n'existe pas dans la base.";
                } else {
                    // Mettre à jour le pseudo
                    $userRepo->updatePseudo($subId, $pseudo);
                    $request->getSession()->set('connected_sub_id', $subId);
                    $request->getSession()->set('connected_pseudo', $pseudo);
                    $success = "Pseudo mis à jour avec succès !";

                    // Redirection vers la page mission
                    return $this->redirectToRoute('mission');
                }
            }
        }

        $dbDebug = null;
        try {
            $check = $userRepo->checkConnection();
            $dbConnected = (bool)($check['ok'] ?? false);
            $dbDebug = $check['payload'] ?? null;

            $users = $dbConnected ? $userRepo->findAll() : [];
        } catch (\Throwable $e) {
            $users = [];
            $dbConnected = false;
            $dbDebug = ['exception' => $e->getMessage()];
            $error ??= 'Impossible de lire la base de données pour le moment.';
        }

        return $this->render('home/index.html.twig', [
            'error' => $error,
            'success' => $success,
            'users' => $users,
            'dbConnected' => $dbConnected,
            'dbDebug' => $dbDebug,
        ]);
    }
}
