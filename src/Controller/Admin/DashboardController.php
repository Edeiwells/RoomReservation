<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Category;
use App\Entity\Equipment;
use App\Entity\Reservation;
use App\Entity\Room;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Doctrine\ORM\EntityManagerInterface;

class DashboardController extends AbstractDashboardController
{
    private AdminUrlGenerator $adminUrlGenerator;
    private EntityManagerInterface $entityManager;

    // je récupère l'AdminUrlGenerator et l'EntityManagerInterface
    public function __construct(AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $entityManager)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->entityManager = $entityManager;
    }

    private function getPendingReservations(): array
    {
        // je récupère la date actuelle
        $currentDate = new \DateTimeImmutable();
        // je définis la date limite à 5 jours
        $thresholdDate = $currentDate->modify('+5 days');
        // je récupère les réservations en attente en fonction de mes paramètres    
        return $this->entityManager->getRepository(Reservation::class)->createQueryBuilder('r')
            ->join('r.user_id', 'u') // jointure avec l'entité User
            ->addSelect('u') // sélectionne également les données de l'utilisateur
            ->where('r.status = :status')
            ->andWhere('r.createdAt < :thresholdDate') // je compare la date de création de la réservation avec la date limite
            ->setParameter('status', 'pending')
            ->setParameter('thresholdDate', $thresholdDate) // je passe la date limite en paramètre
            ->getQuery()
            ->getResult();
    }

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        // je récupère les réservations en attente
        $pendingReservations = $this->getPendingReservations();

        return $this->render('admin/dashboard.html.twig', [
            'pendingReservations' => $pendingReservations,
        ]);
    }

    #[Route('/admin/reservations/pending', name: 'admin_reservations_pending')]
    public function pendingReservations(): Response
    {
        // je récupère les réservations en attente en fonction de mes paramètres 
        $reservations = $this->entityManager->getRepository(Reservation::class)->findBy(['status' => 'pending']);

        return $this->render('admin/pending_reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/admin/reservations/confirm/{id}', name: 'admin_confirm_reservation', methods: ['POST'])]
    public function confirmReservation(Request $request, int $id): Response
    {
        // je récupère la réservation en fonction de l'id
        $reservation = $this->entityManager->getRepository(Reservation::class)->find($id);

        // je vérifie si la réservation existe
        if ($this->isCsrfTokenValid('confirm' . $reservation->getId(), $request->request->get('_token'))) {
            $reservation->setStatus('confirmed');
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('admin_reservations_pending');
    }

    #[Route('/admin/reservations/refuse/{id}', name: 'admin_refuse_reservation', methods: ['POST'])]
    public function refuseReservation(Request $request, int $id): Response
    {
        // je récupère la réservation en fonction de l'id
        $reservation = $this->entityManager->getRepository(Reservation::class)->find($id);

        // je vérifie si la réservation existe
        if ($this->isCsrfTokenValid('refuse' . $reservation->getId(), $request->request->get('_token'))) {
            $reservation->setStatus('refused');
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('admin_reservations_pending');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('ReservationRoom - Administration');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Users');
        yield MenuItem::subMenu('Users', 'fas fa-bars')->setSubItems([
            MenuItem::linkToCrud('Add Users', 'fas fa-plus', User::class)->setAction(Crud::PAGE_NEW),
            MenuItem::linkToCrud('Show Users', 'fas fa-eye', User::class)
        ]);

        yield MenuItem::subMenu('Categories', 'fas fa-bars')->setSubItems([
            MenuItem::linkToCrud('Add Categories', 'fas fa-plus', Category::class)->setAction(Crud::PAGE_NEW),
            MenuItem::linkToCrud('Show Categories', 'fas fa-eye', Category::class)
        ]);

        yield MenuItem::section('Rooms');
        yield MenuItem::subMenu('Actions', 'fas fa-bars')->setSubItems([
            MenuItem::linkToCrud('Add Rooms', 'fas fa-plus', Room::class)->setAction(Crud::PAGE_NEW),
            MenuItem::linkToCrud('Show Rooms', 'fas fa-eye', Room::class),
            MenuItem::linkToCrud('Add Equipment', 'fas fa-plus', Equipment::class)->setAction(Crud::PAGE_NEW),
            MenuItem::linkToCrud('Show Equipment', 'fas fa-eye', Equipment::class)
        ]);

        yield MenuItem::subMenu('Reservation', 'fas fa-bars')->setSubItems([
            MenuItem::linkToCrud('Add reservation', 'fas fa-plus', Reservation::class)->setAction(Crud::PAGE_NEW),
            MenuItem::linkToCrud('Show reservation', 'fas fa-eye', Reservation::class),
            MenuItem::linkToRoute('Pending Reservations', 'fas fa-clock', 'admin_reservations_pending')
        ]);

        yield MenuItem::section('Home');
        yield MenuItem::linkToRoute('Home', 'fas fa-home', 'app_homepage');
    }
}
