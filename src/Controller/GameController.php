<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TakeRepository;
use App\Repository\UserRepository;
use App\Service\SteamAPI;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GameController extends AbstractController
{
    #[Route('/games', name: 'app_game')]
    public function index(SteamAPI $steamAPI, UserRepository $userRepository, TakeRepository $takeRepository): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        /** @var User $user */
        $user = $this->getUser();
        $takesRequired = $user->getTakesRequired();

        $currentUserSteamID = $user->getSteamID() ?? 'not_found';
        if ('not_found' != $currentUserSteamID) {
            $games = $steamAPI->getUserGames($currentUserSteamID);
            $summary = $steamAPI->getUserSummary($currentUserSteamID);
            $avatar = $summary['avatarmedium'];
            $username = $summary['personaname'];
        } else {
            $games = $avatar = $username = null;
        }

        $takesFinished = $takeRepository->getFinishedTakesFromUser($user->getId());

        return $this->render('game/index.html.twig', [
            'games' => $games,
            'avatar' => $avatar,
            'username' => $username,
            'takesFinished' => $takesFinished,
            'takesRequired' => $takesRequired,
        ]);
    }

    #[Route('/games/random', name: 'app_game_random')]
    public function randomGame(SteamAPI $steamAPI, TakeRepository $takeRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $takesFinished = $takeRepository->getFinishedTakesFromUser($user->getId());
        $finished = false;
        foreach ($takesFinished as $take) {
            if ($take->getFinishDate()->format('Y-m-d') == (new \DateTime('now'))->format('Y-m-d')) {
                $finished = true;
                break;
            }
        }

        if (!$finished) {
            return $this->redirectToRoute('app_take_random');
        }

        $currentUserSteamID = $user->getSteamID() ?? 'not_found';
        $games = $steamAPI->getUserGames($currentUserSteamID);
        $randomGame = $games[array_rand($games)];

        return $this->render('game/show.html.twig', [
            'game' => $randomGame,
        ]);
    }
}
