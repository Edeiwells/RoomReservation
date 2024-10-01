<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class MainController extends AbstractController
{ // route pour afficher la page d'accueil
    #[Route('/', name: 'app_homepage')]
    public function index(): Response
    {

        return $this->render('main/homepage.html.twig', [
            'controller_name' => 'MainController',
        ]);
    }
}
