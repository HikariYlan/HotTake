<?php

namespace App\Controller;

use App\Entity\Take;
use App\Entity\User;
use App\Enum\Status;
use App\Form\TakeFormType;
use App\Repository\TakeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TakeController extends AbstractController
{
    #[Route('/sticky_board', name: 'app_sticky_board')]
    public function index(TakeRepository $takeRepository): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        /** @var User $user */
        $user = $this->getUser();
        $takes = $takeRepository->getTakesFromUser($user->getId());
        $pending = $toDo = $onGoing = $finished = [];
        /** @var Take $take */
        foreach ($takes as $take) {
            switch ($take->getStatus()) {
                case Status::PENDING:
                    $pending[] = $take;
                    break;
                case Status::TO_DO:
                    $toDo[] = $take;
                    break;
                case Status::ON_GOING:
                    $onGoing[] = $take;
                    break;
                case Status::FINISHED:
                    $finished[] = $take;
                    break;
            }
        }

        return $this->render('take/index.html.twig', [
            'pending' => $pending,
            'toDo' => $toDo,
            'onGoing' => $onGoing,
            'finished' => $finished,
        ]);
    }

    #[Route('/take/random', name: 'app_take_random', methods: 'GET')]
    public function randomTake(TakeRepository $takeRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $unfinishedTakes = $takeRepository->getUnfinishedTakesFromUser($user->getId());
        if (!$unfinishedTakes) {
            return $this->redirectToRoute('app_sticky_board');
        }

        $randomTake = $unfinishedTakes[array_rand($unfinishedTakes)]->getId();

        return $this->redirectToRoute('app_take_details', [
            'id' => $randomTake,
        ]);
    }

    #[Route('/take/new', name: 'app_take_create', methods: ['GET', 'POST'])]
    public function createTake(Request $request, EntityManagerInterface $entityManager): Response
    {
        $take = new Take();
        $take->setOwner($this->getUser());
        $form = $this->createForm(TakeFormType::class, $take);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($take);
            if (Status::FINISHED === $take->getStatus()) {
                $take->setFinishDate(new \DateTime());
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_sticky_board');
        }

        return $this->render('take/_form.html.twig', [
            'form' => $form,
            'submit_label' => 'Stick it to your board',
        ]);
    }

    #[Route('/take/{id}/edit', name: 'app_take_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('owner', 'take')]
    public function editTake(Take $take, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TakeFormType::class, $take);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($take);
            if (Status::FINISHED === $take->getStatus()) {
                $take->setFinishDate(new \DateTime());
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_sticky_board');
        }

        return $this->render('take/_form.html.twig', [
            'form' => $form,
            'submit_label' => 'Edit your Take',
        ]);
    }

    #[Route('/take/{id}/update-status', name: 'app_take_update_status', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted('owner', 'take')]
    public function updateStatusTake(Take $take, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['status'])) {
            return new JsonResponse(['error' => 'Status is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $statusEnum = Status::from($data['status']);
        } catch (\ValueError $e) {
            return new JsonResponse(['error' => 'Invalid status value'], Response::HTTP_BAD_REQUEST);
        }

        $take->setStatus($statusEnum);

        if (Status::FINISHED === $statusEnum && !$take->getFinishDate()) {
            $take->setFinishDate(new \DateTime());
        }

        if (Status::FINISHED !== $statusEnum && $take->getFinishDate()) {
            $take->setFinishDate(null);
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'take' => [
                'id' => $take->getId(),
                'status' => $take->getStatus()->value,
                'finishDate' => $take->getFinishDate()?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    #[Route('/take/{id}/finish', name: 'app_take_finish', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('owner', 'take')]
    public function finishTake(Take $take, EntityManagerInterface $manager, Request $request): Response
    {
        $submittedToken = $request->getPayload()->get('token');
        if ($this->isCsrfTokenValid('finish-take', $submittedToken)) {
            $take->setStatus(Status::FINISHED);
            $take->setFinishDate(new \DateTime());
            $manager->persist($take);
            $manager->flush();
        }

        return $this->redirectToRoute('app_sticky_board');
    }

    #[Route('/take/{id}/delete', name: 'app_take_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('owner', 'take')]
    public function deleteTake(Take $take, EntityManagerInterface $manager, Request $request): Response
    {
        $submittedToken = $request->getPayload()->get('token');
        if ($this->isCsrfTokenValid('delete-item', $submittedToken)) {
            $manager->remove($take);
            $manager->flush();
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/take/{id}', name: 'app_take_details', requirements: ['id' => '\d+'], methods: 'GET')]
    #[IsGranted('owner', 'take')]
    public function showTake(?Take $take): Response
    {
        if (!$take) {
            return $this->render('take/show.html.twig', [
                'take' => null,
            ]);
        }

        $isAdmin = in_array('ROLE_ADMIN', $this->getUser()->getRoles());
        $isOwner = $take->getOwner() === $this->getUser();

        if (!$isAdmin && !$isOwner) {
            return $this->redirectToRoute('app_sticky_board');
        }

        return $this->render('take/show.html.twig', [
            'take' => $take,
        ]);
    }
}