<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\HotelRepository;

final class HotelListController extends AbstractController
{
    #[Route('/hotel/list', name: 'app_hotel_list')]
    public function index(Request $request, HotelRepository $hotelRepository): Response
    {
        $searchTerm = $request->query->get('search');

        if ($searchTerm) {
            $hotels = $hotelRepository->createQueryBuilder('h')
                ->where('LOWER(h.name) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $searchTerm . '%')
                ->getQuery()
                ->getResult();
        } else {
            $hotels = $hotelRepository->findAll();
        }

        return $this->render('hotel_list/index.html.twig', [
            'hotels' => $hotels,
            'searchTerm' => $searchTerm,
        ]);
    }
}
