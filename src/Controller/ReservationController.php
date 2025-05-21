<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\HotelRepository;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReservationController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/reservation/new', name: 'app_reservation_new')]
    public function new(Request $request, HotelRepository $hotelRepository, RoomRepository $roomRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $hotelId = $request->query->get('hotelId');
        $availableRooms = [];
        $hotel = null;

        if ($hotelId) {
            $hotel = $hotelRepository->find($hotelId);
            if ($hotel) {
                $availableRooms = $hotel->getRooms()->toArray();
            } else {
                return $this->redirectToRoute('app_hotel_list');
            }
        }

        if ($request->isMethod('POST')) {
            $clientName = $request->request->get('clientName');
            $startDateStr = $request->request->get('startDate');
            $endDateStr = $request->request->get('endDate');
            $roomId = $request->request->get('room');

            $room = $roomRepository->find($roomId);
            if (!$room) {
                return $this->redirectToRoute('app_reservation_new', ['hotelId' => $hotelId]);
            }

            try {
                $startDate = new \DateTime($startDateStr);
                $endDate = new \DateTime($endDateStr);
            } catch (\Exception $e) {
                return $this->redirectToRoute('app_reservation_new', ['hotelId' => $hotelId]);
            }

            if ($endDate <= $startDate) {
                return $this->redirectToRoute('app_reservation_new', ['hotelId' => $hotelId]);
            }

            // Vérifier si la chambre est déjà réservée sur cet intervalle
            $qb = $this->em->getRepository(Reservation::class)->createQueryBuilder('r');
            $qb->where('r.room = :room')
                ->andWhere('(:startDate < r.endDate AND :endDate > r.startDate)')
                ->setParameter('room', $room)
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);
            $existingReservation = $qb->getQuery()->getOneOrNullResult();
            if ($existingReservation) {
                $this->addFlash('error', 'This room is already booked for the selected dates.');
                return $this->redirectToRoute('app_reservation_new', ['hotelId' => $hotelId]);
            }

            $reservation = new Reservation();
            $reservation->setClientName($clientName);
            $reservation->setClientEmail($user->getUserIdentifier());
            $reservation->setStartDate($startDate);
            $reservation->setEndDate($endDate);
            $reservation->setRoom($room);
            $reservation->setUser($user);

            $this->em->persist($reservation);
            $this->em->flush();

            return $this->redirectToRoute('app_user_reservations');
        }

        return $this->render('reservation/index.html.twig', [
            'availableRooms' => $availableRooms,
            'hotel' => $hotel,
        ]);
    }
}
