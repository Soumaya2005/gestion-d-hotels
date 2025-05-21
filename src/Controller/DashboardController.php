<?php

namespace App\Controller;

use App\Entity\Hotel;
use App\Entity\Room;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\RoomType;
use App\Repository\HotelRepository;
use App\Repository\RoomRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET', 'POST'])]
    public function index(
        HotelRepository $hotelRepository,
        RoomRepository $roomRepository,
        ReservationRepository $reservationRepository,
        UserRepository $userRepository,
        Request $request
    ): Response {
        // Générer les données d'analytiques
        $analyticsData = $this->generateAnalyticsData($hotelRepository, $roomRepository, $reservationRepository);
        // Traitement des actions de suppression
        $deleteHotelId = $request->query->get('delete_hotel');
        if ($deleteHotelId) {
            return $this->deleteHotel($deleteHotelId, $hotelRepository);
        }
        
        $deleteRoomId = $request->query->get('delete_room');
        if ($deleteRoomId) {
            return $this->deleteRoom($deleteRoomId, $roomRepository);
        }
        
        $deleteUserId = $request->query->get('delete_user');
        if ($deleteUserId) {
            return $this->deleteUser($deleteUserId, $userRepository);
        }
        
        // Traitement des actions d'édition
        $editHotelId = $request->query->get('edit_hotel');
        $editHotel = null;
        if ($editHotelId) {
            $editHotel = $hotelRepository->find($editHotelId);
        }
        
        $editRoomId = $request->query->get('edit_room');
        $editRoom = null;
        if ($editRoomId) {
            $editRoom = $roomRepository->find($editRoomId);
        }
        
        $editReservationId = $request->query->get('edit_reservation');
        $editReservation = null;
        if ($editReservationId) {
            $editReservation = $reservationRepository->find($editReservationId);
        }

        // Gestion de l'édition ou de la création d'une réservation
        if ($request->isMethod('POST') && ($request->request->has('clientName') || $request->request->has('clientEmail'))) {
            $reservation = $editReservation ?? new Reservation();
            $reservation->setClientName($request->request->get('clientName'));
            $reservation->setClientEmail($request->request->get('clientEmail'));
            $startDate = $request->request->get('startDate');
            $endDate = $request->request->get('endDate');
            $roomId = $request->request->get('room');
            $room = $roomRepository->find($roomId);
            if ($room) {
                $reservation->setRoom($room);
            }
            if ($startDate) {
                $reservation->setStartDate(new \DateTime($startDate));
            }
            if ($endDate) {
                $reservation->setEndDate(new \DateTime($endDate));
            }
            $this->entityManager->persist($reservation);
            $this->entityManager->flush();
            return $this->redirectToRoute('app_dashboard', ['_fragment' => 'reservations']);
        }
        
        $editUserId = $request->query->get('edit_user');
        $editUser = null;
        if ($editUserId) {
            $editUser = $userRepository->find($editUserId);
        }
        
        $hotelSearch = $request->query->get('hotel_search');
        $roomSearch = $request->query->get('room_search');
        $roomHotelSearch = $request->query->get('room_hotel_search');
        $resHotelSearch = $request->query->get('res_hotel_search');
        $resRoomSearch = $request->query->get('res_room_search');
        $resUserSearch = $request->query->get('res_user_search');
        $userSearch = $request->query->get('user_search');

        $hotels = $hotelRepository->createQueryBuilder('h')
            ->where(':search IS NULL OR LOWER(h.name) LIKE LOWER(:search)')
            ->setParameter('search', $hotelSearch ? "%$hotelSearch%" : null)
            ->getQuery()->getResult();

        $rooms = $roomRepository->createQueryBuilder('r')
            ->join('r.hotel', 'h')
            ->where('(:num IS NULL OR r.number LIKE :num) AND (:hname IS NULL OR LOWER(h.name) LIKE LOWER(:hname))')
            ->setParameter('num', $roomSearch ? "%$roomSearch%" : null)
            ->setParameter('hname', $roomHotelSearch ? "%$roomHotelSearch%" : null)
            ->getQuery()->getResult();

        $reservations = $reservationRepository->createQueryBuilder('res')
            ->join('res.room', 'r')
            ->join('r.hotel', 'h')
            ->join('res.user', 'u')
            ->where('(:hotel IS NULL OR LOWER(h.name) LIKE LOWER(:hotel)) AND (:room IS NULL OR r.number LIKE :room) AND (:email IS NULL OR u.email LIKE :email)')
            ->setParameter('hotel', $resHotelSearch ? "%$resHotelSearch%" : null)
            ->setParameter('room', $resRoomSearch ? "%$resRoomSearch%" : null)
            ->setParameter('email', $resUserSearch ? "%$resUserSearch%" : null)
            ->getQuery()->getResult();
            
        $users = $userRepository->createQueryBuilder('u')
            ->where(':search IS NULL OR LOWER(u.email) LIKE LOWER(:search)')
            ->setParameter('search', $userSearch ? "%$userSearch%" : null)
            ->getQuery()->getResult();

        // Gestion de l'édition ou de la création d'un hôtel
        $hotel = $editHotel ?? new Hotel();
        if ($request->isMethod('POST') && $request->request->has('name')) {
            $hotel->setName($request->request->get('name'));
            $hotel->setAddress($request->request->get('address'));
            $imageFile = $request->files->get('image');

            if ($imageFile) {
                // Si on modifie un hôtel avec une image existante, supprimer l'ancienne image
                if ($hotel->getId() && $hotel->getImage()) {
                    $oldImagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/hotels/' . $hotel->getImage();
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/hotels',
                        $newFilename
                    );
                    $hotel->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Image upload failed.');
                }
            }

            $this->entityManager->persist($hotel);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_dashboard');
        }

        // Gestion de l'édition ou de la création d'une chambre
        $room = $editRoom ?? new Room();
        $roomForm = $this->createForm(RoomType::class, $room);
        $roomForm->handleRequest($request);
        
        // Vérifier si c'est bien le formulaire de chambre qui est soumis
        if ($request->isMethod('POST') && $roomForm->isSubmitted() && $roomForm->isValid()) {
            try {
                $this->entityManager->persist($room);
                $this->entityManager->flush();
                $this->addFlash('success', 'Chambre enregistrée avec succès !');
                return $this->redirectToRoute('app_dashboard', ['_fragment' => 'rooms']);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement de la chambre.');
            }
        }
        
        // Gestion de l'édition ou de la création d'un utilisateur
        $user = $editUser ?? new User();
        if ($request->isMethod('POST') && $request->request->has('email') && !$roomForm->isSubmitted()) {
            // Vérifier si c'est le formulaire utilisateur et pas un autre
            if (!$request->request->has('name') || $request->request->has('roles')) {
                $user->setEmail($request->request->get('email'));
                
                // Gestion du mot de passe
                $password = $request->request->get('password');
                if ($password) {
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $user->setPassword($hashedPassword);
                } elseif (!$editUser) {
                    // Si c'est un nouvel utilisateur, le mot de passe est obligatoire
                    $this->addFlash('error', 'Le mot de passe est obligatoire pour un nouvel utilisateur.');
                    return $this->redirectToRoute('app_dashboard', ['edit_user' => $editUser ? $editUser->getId() : null]);
                }
                
                // Gestion des rôles
                $roles = $request->request->all('roles');
                if (!empty($roles)) {
                    $user->setRoles($roles);
                } else {
                    $user->setRoles(['ROLE_USER']);
                }
                
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                
                return $this->redirectToRoute('app_dashboard', ['_fragment' => 'users']);
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'hotels' => $hotels,
            'rooms' => $rooms,
            'reservations' => $reservations,
            'users' => $users,
            'editHotel' => $editHotel,
            'editRoom' => $editRoom,
            'editReservation' => $editReservation,
            'editUser' => $editUser,
            'roomForm' => $roomForm->createView(),
            'search' => [
                'hotel' => $hotelSearch,
                'room' => $roomSearch,
                'roomHotel' => $roomHotelSearch,
                'resHotel' => $resHotelSearch,
                'resRoom' => $resRoomSearch,
                'resUser' => $resUserSearch,
                'user' => $userSearch,
            ],
            'analytics' => $analyticsData
        ]);
    }

    #[Route('/dashboard/reservation/cancel/{id}', name: 'dashboard_reservation_cancel')]
    public function cancelReservation(int $id, ReservationRepository $reservationRepository): Response
    {
        $reservation = $reservationRepository->find($id);
        if ($reservation) {
            $this->entityManager->remove($reservation);
            $this->entityManager->flush();
            $this->addFlash('success', 'Reservation has been deleted successfully.');
        } else {
            $this->addFlash('error', 'Reservation not found.');
        }

        return $this->redirectToRoute('app_dashboard', ['_fragment' => 'reservations']);
    }
    
    /**
     * Supprime un hôtel
     */
    private function deleteHotel(int $id, HotelRepository $hotelRepository): Response
    {
        $hotel = $hotelRepository->find($id);
        if ($hotel) {
            // Vérifier si l'hôtel a des chambres
            $rooms = $hotel->getRooms();
            if (count($rooms) > 0) {
                return $this->redirectToRoute('app_dashboard');
            }
            
            // Supprimer l'image si elle existe
            if ($hotel->getImage()) {
                $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/hotels/' . $hotel->getImage();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            $this->entityManager->remove($hotel);
            $this->entityManager->flush();
        }
        
        return $this->redirectToRoute('app_dashboard');
    }
    
    /**
     * Supprime une chambre
     */
    private function deleteRoom(int $id, RoomRepository $roomRepository): Response
    {
        $room = $roomRepository->find($id);
        if ($room) {
            // Vérifier si la chambre a des réservations
            $reservations = $room->getReservations();
            if (count($reservations) > 0) {
                $this->addFlash('error', "Impossible de supprimer cette chambre car elle a des réservations.");
                return $this->redirectToRoute('app_dashboard');
            }
            
            $this->entityManager->remove($room);
            $this->entityManager->flush();
            
        }
        
        return $this->redirectToRoute('app_dashboard');
    }
    
    /**
     * Supprime un utilisateur
     */
    private function deleteUser(int $id, UserRepository $userRepository): Response
    {
        $user = $userRepository->find($id);
        if ($user) {
            // Vérifier si l'utilisateur a des réservations
            $reservations = $user->getReservations();
            if (count($reservations) > 0) {
                $this->addFlash('error', 'Impossible de supprimer cet utilisateur car il a des réservations.');
                return $this->redirectToRoute('app_dashboard');
            }
            
            $this->entityManager->remove($user);
            $this->entityManager->flush();
            $this->addFlash('success', 'Utilisateur supprimé avec succès.');
        }
        
        return $this->redirectToRoute('app_dashboard');
    }
    
    /**
     * Génère les données d'analytiques pour le tableau de bord
     */
    private function generateAnalyticsData(
        HotelRepository $hotelRepository, 
        RoomRepository $roomRepository, 
        ReservationRepository $reservationRepository
    ): array {
        // Statistiques générales
        $totalHotels = count($hotelRepository->findAll());
        $totalRooms = count($roomRepository->findAll());
        $totalReservations = count($reservationRepository->findAll());
        
        // Taux d'occupation actuel
        $occupiedRooms = count($reservationRepository->findActiveReservations());
        $occupancyRate = ($totalRooms > 0) ? round(($occupiedRooms / $totalRooms) * 100) : 0;
        
        // Données mensuelles pour les graphiques
        $currentYear = (new \DateTime())->format('Y');
        $monthlyReservations = [];
        $monthlyRevenue = [];
        
        // Initialiser les tableaux avec des zéros pour chaque mois
        for ($i = 1; $i <= 12; $i++) {
            $monthlyReservations[$i] = 0;
            $monthlyRevenue[$i] = 0;
        }
        
        // Remplir les données mensuelles
        $allReservations = $reservationRepository->findAll();
        foreach ($allReservations as $reservation) {
            $startDate = $reservation->getStartDate();
            if ($startDate && $startDate->format('Y') == $currentYear) {
                $month = (int)$startDate->format('m');
                $monthlyReservations[$month]++;
                
                // Calculer le revenu (en supposant que room->getPrice() existe)
                $room = $reservation->getRoom();
                if ($room) {
                    $price = $room->getPrice() ?? 0;
                    $nights = 1; // Par défaut
                    $endDate = $reservation->getEndDate();
                    if ($endDate) {
                        $nights = max(1, $startDate->diff($endDate)->days);
                    }
                    $monthlyRevenue[$month] += ($price * $nights);
                }
            }
        }
        
        // Top hôtels par nombre de réservations
        $hotelReservations = [];
        foreach ($allReservations as $reservation) {
            $room = $reservation->getRoom();
            if ($room) {
                $hotel = $room->getHotel();
                if ($hotel) {
                    $hotelId = $hotel->getId();
                    if (!isset($hotelReservations[$hotelId])) {
                        $hotelReservations[$hotelId] = [
                            'name' => $hotel->getName(),
                            'count' => 0
                        ];
                    }
                    $hotelReservations[$hotelId]['count']++;
                }
            }
        }
        
        // Trier par nombre de réservations (décroissant)
        uasort($hotelReservations, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        
        // Limiter à 5 hôtels
        $topHotels = array_slice($hotelReservations, 0, 5, true);
        
        // Réservations récentes (triées par date de début)
        $recentReservations = $reservationRepository->findBy([], ['startDate' => 'DESC'], 5);
        
        return [
            'totalHotels' => $totalHotels,
            'totalRooms' => $totalRooms,
            'totalReservations' => $totalReservations,
            'occupancyRate' => $occupancyRate,
            'monthlyReservations' => array_values($monthlyReservations),
            'monthlyRevenue' => array_values($monthlyRevenue),
            'topHotels' => $topHotels,
            'recentReservations' => $recentReservations
        ];
    }
}
