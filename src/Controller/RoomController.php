<?php

namespace App\Controller;

use App\Entity\Room;
use App\Repository\EquipmentRepository;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class RoomController extends AbstractController
{
    #[Route('/reserver', name: 'app_room', methods: ['GET'])]
    public function index(Request $request, RoomRepository $roomRepository, EntityManagerInterface $entityManager, EquipmentRepository $equipmentRepository): Response
    {
        // Récupérer les équipements
        $equipments = $equipmentRepository->findAll();
        // récupérer les équipements sélectionnés
        $equipmentIds = $request->query->all('equipments');

        $rooms = [];
        // affichage des salles avec les équipements sélectionnés en fonction des paramètres de l'URL
        if (!empty($equipmentIds)) {
            $queryBuilder = $entityManager->createQueryBuilder();
            $queryBuilder->select('r')
                ->from(Room::class, 'r')
                ->join('r.equipments', 'e')
                ->where('r.available = :available')
                ->andWhere($queryBuilder->expr()->in('e.id', ':equipments'))
                ->setParameter('available', true)
                ->setParameter('equipments', $equipmentIds);

            $rooms = $queryBuilder->getQuery()->getResult();
        } else {
            $rooms = $roomRepository->findBy(['available' => true]);
        }

        return $this->render('room/room.html.twig', [
            'controller_name' => 'RoomController',
            'rooms' => $rooms,
            'equipments' => $equipments,
        ]);
    }

    #[Route('/reserver/add/{id}', name: 'app_room_add', methods: ['GET'])]
    public function addReservation($id, SessionInterface $session): Response
    {

        // Récupérer les réservations en cours
        $reservations = $session->get('reservations', []);
        $reservations[$id] = $id;
        // Enregistrer la réservation en cours
        $session->set('reservations', $reservations);
        return $this->redirectToRoute('app_room');
    }

    #[Route('/reserver/{id}', name: 'app_room_show', methods: ['GET'])]
    public function show(Room $room): Response
    {
        // Récupérer les équipements associés à la salle
        $equipments = $room->getEquipments();

        return $this->render('room/show.html.twig', [
            'room' => $room,
            'equipments' => $equipments,
        ]);
    }
}
