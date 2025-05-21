<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserReservationsController extends AbstractController
{
    #[Route('/user/reservations', name: 'app_user_reservations')]
    public function index(
        Request $request,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $search = $request->query->get('search');
        $editId = $request->query->get('edit');

        $queryBuilder = $reservationRepository->createQueryBuilder('r')
            ->join('r.room', 'room')
            ->join('room.hotel', 'hotel')
            ->where('r.user = :user')
            ->setParameter('user', $user);

        if ($search) {
            $queryBuilder
                ->andWhere('LOWER(hotel.name) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $reservations = $queryBuilder->getQuery()->getResult();

        $form = null;

        if ($editId) {
            $reservationToEdit = $reservationRepository->find($editId);

            if ($reservationToEdit && $reservationToEdit->getUser() === $user) {
                $form = $this->createForm(\App\Form\ReservationType::class, $reservationToEdit);
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
                    $room = $reservationToEdit->getRoom();
                    $startDate = $reservationToEdit->getStartDate();
                    $endDate = $reservationToEdit->getEndDate();

                    // Vérifier conflit sur l'intervalle
                    $conflict = $reservationRepository->createQueryBuilder('r')
                        ->andWhere('r.room = :room')
                        ->andWhere('(:startDate < r.endDate AND :endDate > r.startDate)')
                        ->andWhere('r.id != :id')
                        ->setParameter('room', $room)
                        ->setParameter('startDate', $startDate)
                        ->setParameter('endDate', $endDate)
                        ->setParameter('id', $reservationToEdit->getId())
                        ->getQuery()
                        ->getOneOrNullResult();

                    if ($conflict) {
                        $form->addError(new \Symfony\Component\Form\FormError('Cette chambre est déjà réservée pour cet intervalle de dates.'));
                    } else {
                        $em->flush();
                        $this->addFlash('success', 'Réservation modifiée avec succès.');
                        return $this->redirectToRoute('app_user_reservations');
                    }
                }
            }
        }

        return $this->render('user_reservations/index.html.twig', [
            'reservations' => $reservations,
            'form' => $form ? $form->createView() : null,
            'editId' => $editId,
            'searchTerm' => $search,
        ]);
    }

    #[Route('/user/reservations/cancel/{id}', name: 'app_user_reservations_cancel')]
    public function cancel(int $id, ReservationRepository $reservationRepository, EntityManagerInterface $em): Response
    {
        $reservation = $reservationRepository->find($id);

        if (!$reservation || $reservation->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Reservation not found or access denied.');
            return $this->redirectToRoute('app_user_reservations');
        }

        $em->remove($reservation);
        $em->flush();

        $this->addFlash('success', 'Reservation canceled.');
        return $this->redirectToRoute('app_user_reservations');
    }

    #[Route('/user/reservations/delete/{id}', name: 'app_user_reservations_delete')]
    public function deleteRoom(int $id, RoomRepository $roomRepository, EntityManagerInterface $em): Response {
        $room = $roomRepository->find($id);

        if (!$room) {
            $this->addFlash('error', 'Chambre non trouvée.');
            return $this->redirectToRoute('app_user_reservations');
        }

        // Log the room ID being deleted
        $this->addFlash('info', "Tentative de suppression de la chambre avec l'ID : $id");

        // Supprimer la chambre directement
        $em->remove($room);
        $em->flush();

        $this->addFlash('success', 'Chambre supprimée avec succès.');
        return $this->redirectToRoute('app_user_reservations');
    }
}
