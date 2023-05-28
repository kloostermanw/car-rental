<?php

namespace App\Controller;

use App\Classes\BookReservation;
use App\Entity\Car;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\ReservationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class ReservationController extends AbstractController
{

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/reservation/create', name: 'reservation_create')]
    public function create(Request $request, UserInterface $user): Response
    {
        $reservation = new Reservation();
        $form = $this->createForm(ReservationFormType::class, $reservation);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Reservation $objReservation */
            $objReservation = $form->getData();

            if ($user instanceof User) {
                $objReservation->setUser($user);
            }

            $arrResult = $this->createBooking($objReservation);

            return $this->render('reservation/show.html.twig', [
                'result' => $arrResult
            ]);
        }

        return $this->render('reservation/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    protected function createBooking(Reservation $reservation): array
    {
        //*** Find car ids that are NOT available for this period.
        $query = $this->em->createQuery(
            'select DISTINCT IDENTITY(r.car) from App\Entity\Reservation r
                where r.startDate < :end
                and r.endDate > :start'
        )->setParameters(['start' => '2023-05-05', 'end' => '2023-05-15']);

        $result = $query->getScalarResult();
        $ids = array_column($result, "1");


        //*** Get cars that have not these ids.
        $result = $this->em->createQuery(
            'SELECT c FROM App\Entity\Car c
             WHERE c.id NOT IN (:ids)'
            )
            ->setParameter('ids', $ids, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
            ->getResult();

        dd($result);

        return [];
    }
}
