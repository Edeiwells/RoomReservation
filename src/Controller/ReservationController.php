<?php

namespace App\Controller;

use App\Entity\Room;

use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Form\ReservationTimeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;
use Symfony\Component\HttpFoundation\RequestStack;

class ReservationController extends AbstractController
{
    private $security;
    private $requestStack;

    // Injecter les services Security et RequestStack
    public function __construct(SecurityBundleSecurity $security, RequestStack $requestStack)
    {
        $this->security = $security;
        $this->requestStack = $requestStack;
    }


    #[Route('/reservation/{roomId}', name: 'app_reservation')]
    public function index(Request $request, int $roomId, EntityManagerInterface $entityManager): Response
    {
        // Créer un formulaire de réservation
        $form = $this->createForm(ReservationType::class);
        // On envoie la requete
        $form->handleRequest($request);


        // si le form est submit et valid alors on recupere la date
        if ($form->isSubmitted() && $form->isValid()) {
            $date = $form->get('date')->getData()->format('Y-m-d');

            // ajouter une condition pour la date choisie si celle-ci est inférieure à la date actuelle alors on affiche une erreur sur la réservation

            // On stock la date dans la session
            $this->requestStack->getSession()->set('reservation_date', $date);

            // on redirige vers la page de reservation
            return $this->redirectToRoute('app_reservation_recap', [
                'roomId' => $roomId,
            ]);
        }

        // si le form n'est pas submit on affiche le formulaire
        return $this->render('reservation/reservation.html.twig', [
            'controller_name' => 'ReservationController',
            'form' => $form->createView(),
            'roomId' => $roomId,
        ]);
    }

    // fonction pour ajouter dans la base de donnée la reservation
    public function handleform($form, int $roomId, EntityManagerInterface $entityManager, string $date): Reservation
    {
        // Récupérer l'utilisateur actuellement connecté
        $user = $this->security->getUser();

        // Récupérer l'heure de réservation depuis le formulaire (DateTimeImmutable)
        $reservationTime = $form->get('createdAt')->getData();

        // Récupérer la salle à partir de l'ID
        $room = $entityManager->getRepository(Room::class)->find($roomId);

        // Créer la date et l'heure de début de la réservation en fusionnant date et heure
        $startDateTime = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $reservationTime); // 2024-09-30 14h40

        // Ajouter 2 heures pour obtenir la date et l'heure de fin
        $endDateTime = $startDateTime->modify('+1 hour'); // 2024-09-30 14h40 + 1h = 2024-09-30 15h40

        // Créer une nouvelle réservation et associer l'utilisateur et la salle
        $reservation = new Reservation();
        $reservation->setRoomId($room); // id de la salle    
        $reservation->setUserId($user); // id de l'utilisateur
        $reservation->setDate(new \DateTimeImmutable()); // Date actuelle
        $reservation->setStatus('pending'); // Statut par défaut
        $reservation->setCreatedAt($startDateTime); // Objet DateTimeImmutable
        $reservation->setEndAt($endDateTime); // Objet DateTimeImmutable

        // Enregistrer la réservation dans la base de données
        $entityManager->persist($reservation);
        $entityManager->flush();

        return $reservation;
    }

    #[Route('/reservation/recap/{roomId}', name: 'app_reservation_recap')]
    public function recap(Request $request, int $roomId, EntityManagerInterface $entityManager): Response
    {
        // Récupérer la date de la session
        $dateString = $this->requestStack->getSession()->get('reservation_date');

        // Convertir la chaîne de caractères en DateTimeImmutable
        try {
            $date = new \DateTimeImmutable($dateString);
        } catch (\Exception $e) {
            // Gérer l'erreur si la conversion échoue
            throw new \InvalidArgumentException('Invalid date format');
        }

        // Convertir la date en chaîne de caractères avant d'appeler getAvailableSlots
        $availableSlots = $this->getAvailableSlots($roomId, $entityManager, $date);
        $form = $this->createForm(ReservationTimeType::class, null, [
            'available_slots' => $availableSlots,
            'date' => $dateString, // Passer la date comme option
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reservation = $this->handleform($form, $roomId, $entityManager, $dateString);

            // Rediriger vers la page de confirmation avec les détails de la réservation
            return $this->redirectToRoute('app_reservation_details', [
                'reservationId' => $reservation->getId(),
            ]);
        }

        return $this->render('reservation/reservation_time.html.twig', [
            'controller_name' => 'ReservationController',
            'form' => $form->createView(),
            'roomId' => $roomId,
            'date' => $dateString, // Gardez la chaîne de caractères pour l'affichage
            'availableSlots' => $availableSlots,
        ]);
    }

    #[Route('/reservation/details/{reservationId}', name: 'app_reservation_details')]
    public function details(int $reservationId, EntityManagerInterface $entityManager): Response
    {
        // Récupérez la réservation à partir de l'ID
        $reservation = $entityManager->getRepository(Reservation::class)->find($reservationId);

        // Récupérez la salle associée à la réservation
        $room = $reservation->getRoomId();

        return $this->render('reservation/details.html.twig', [
            'reservation' => $reservation,
            'room' => $room,
        ]);
    }

    #[Route('/reservations', name: 'app_user_reservations')]
    public function userReservations(EntityManagerInterface $entityManager): Response
    {
        // Récupérez l'utilisateur actuellement connecté
        $user = $this->security->getUser();
        // dd($user);

        // Récupérez les réservations de l'utilisateur connecté
        $reservations = $entityManager->getRepository(Reservation::class)->findBy(['user_id' => $user]);
        return $this->render('reservation/user_reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/reservation/edit/{id}', name: 'app_reservation_edit')]
    public function edit(Request $request, int $id, EntityManagerInterface $entityManager): Response
    {
        // Récupérer la réservation à partir de l'ID
        $reservation = $entityManager->getRepository(Reservation::class)->find($id);

        // Récupérer la date de la réservation
        $date = $reservation->getCreatedAt();

        // Charger les créneaux horaires disponibles pour la date
        $availableSlots = $this->getAvailableSlots($reservation->getRoomId()->getId(), $entityManager, $date);

        // Créer le formulaire avec les créneaux horaires disponibles
        $form = $this->createForm(ReservationTimeType::class, null, [
            'available_slots' => $availableSlots,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer le créneau horaire sélectionné depuis le formulaire
            $selectedTime = $form->get('createdAt')->getData();

            // Créer la nouvelle date et heure de début de la réservation
            $startDateTime = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $selectedTime);

            // Ajouter 2 heures pour obtenir la date et l'heure de fin
            $endDateTime = $startDateTime->modify('+1 hour');

            // Mettre à jour la réservation avec les nouvelles valeurs
            $reservation->setCreatedAt($startDateTime);
            $reservation->setEndAt($endDateTime);

            $entityManager->flush();

            return $this->redirectToRoute('app_user_reservations');
        }

        return $this->render('reservation/edit.html.twig', [
            'form' => $form->createView(),
            'reservation' => $reservation,
        ]);
    }

    #[Route('/reservation/delete/{id}', name: 'app_reservation_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, EntityManagerInterface $entityManager): Response
    {
        // Récupérer la réservation à partir de l'ID
        $reservation = $entityManager->getRepository(Reservation::class)->find($id);

        // Vérifier si le jeton CSRF est valide
        if ($this->isCsrfTokenValid('delete' . $reservation->getId(), $request->request->get('_token'))) {
            $entityManager->remove($reservation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_user_reservations');
    }

    // Fonction pour récupérer les créneaux horaires disponibles pour une salle et une date donnée
    private function getAvailableSlots(int $roomId, EntityManagerInterface $entityManager, \DateTimeImmutable $date): array
    {

        // Récupérer toutes les réservations pour la salle donnée
        $reservations = $entityManager->getRepository(Reservation::class)->findBy(['room_id' => $roomId]);

        $reservedSlots = [];

        // Parcourir les réservations pour la salle et la date donnée
        foreach ($reservations as $reservation) {
            // Vérifier si la réservation est confirmée et a lieu le même jour
            if ($reservation->getCreatedAt()->format('Y-m-d') === $date->format('Y-m-d') && $reservation->getStatus() === 'confirmed') {

                $start = $reservation->getCreatedAt();
                $end = $reservation->getEndAt();
                $interval = new \DateInterval('PT15M'); // Intervalle de 15 minutes
                // Créer un objet DatePeriod pour obtenir tous les créneaux horaires réservés
                $period = new \DatePeriod($start, $interval, $end);

                // Ajouter les créneaux horaires réservés à la liste
                foreach ($period as $dateTime) {
                    $reservedSlots[] = $dateTime->format('H:i');
                }
            }
        }

        $availableSlots = [];
        $hours = range(8, 16);
        $minutes = [0];

        // Générer tous les créneaux horaires disponibles
        foreach ($hours as $hour) {
            foreach ($minutes as $minute) {
                $slot = sprintf('%02d:%02d', $hour, $minute);
                if (!in_array($slot, $reservedSlots)) {
                    $availableSlots[] = $slot;
                }
            }
        }

        return $availableSlots;
    }
}
