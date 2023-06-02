<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class DashboardController extends AbstractController
{
    /**
     * @var Security
     */
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[Route('/', name: 'dashboard.index')]
    public function index(): Response
    {
        $objUser = $this->security->getUser();

        return $this->render('dashboard/index.html.twig', [
            'reservations' => ($objUser instanceof User) ? $objUser->getReservations() : [],
        ]);
    }
}
