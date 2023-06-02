<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\ReservationFormType;
use DateInterval;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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

        if ($form->isSubmitted()) {
            /** @var Reservation $objReservation */
            $objReservation = $form->getData();

            if (!$this->canUserMakeReservation($user, $objReservation->getStartDate())) {
                $form->addError(new FormError('You are limited to rente a car once every 30 days.'));
            }
                if ($user instanceof User) {
                    $objReservation->setUser($user);
                }

                $objReservation = $this->createBooking($objReservation);

                if (!$objReservation->getCar() instanceof Car) {
                    $form->addError(new FormError('No car found for this period.'));
                }

            if ($form->isValid()) {
                $this->em->persist($objReservation);
                $this->em->flush();

                return $this->redirectToRoute('dashboard.index');
            }
        }

        return $this->render('reservation/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reservation/delete/{id}', name: 'reservation_delete')]
    public function destroy(UserInterface $user, $id): Response
    {
        $objReservation = $this->em->getRepository(Reservation::class)->Find($id);

        if ($objReservation instanceof Reservation) {
            $this->em->remove($objReservation);
            $this->em->flush();
        }

        return $this->redirectToRoute('dashboard.index');
    }

    protected function createBooking(Reservation $reservation): Reservation
    {
        //*** Find car ids that are NOT available for this period.
        $query = $this->em->createQuery(
            'select DISTINCT IDENTITY(r.car) from App\Entity\Reservation r
                where r.startDate < :end
                and r.endDate > :start'
            )
            ->setParameters(['start' => $reservation->getStartDate(), 'end' => $reservation->getEndDate()]);

        $result = $query->getScalarResult();
        $ids = array_column($result, "1");

        $qb = $this->em->createQueryBuilder();
        $qb->select('car')
            ->from(Car::class, 'car')
            ->leftJoin('car.category', 'cat');
        if (count($ids) > 0) {
            $qb->where($qb->expr()->notIn('car.id', ':ids'))
                ->setParameter('ids', $ids);
        }
        $qb->andWhere('cat.numberOfPersons >= :persons')
            ->orderBy('cat.numberOfPersons')
            ->setParameter('persons', $reservation->getNumberOfPersons())
            ->setMaxResults(1);


        if (count($qb->getQuery()->getResult())) {
            $objCar = $qb->getQuery()->getSingleResult();

            if ($objCar instanceof Car) {
                $reservation->setCar($objCar);
            }
        }

        return $reservation;
    }

    protected function canUserMakeReservation(UserInterface $user, ?DateTimeInterface $objDateTime): bool
    {
        $blnReturn = true;

        $qb = $this->em->createQueryBuilder();
        $qb->select('reservation')
            ->from(Reservation::class, 'reservation')
            ->where('reservation.user = :user')
            ->orderBy('reservation.endDate')
            ->setParameter('user', $user)
            ->setMaxResults(1);

        if (count($qb->getQuery()->getResult())) {
            $objReservation = $qb->getQuery()->getSingleResult();

            if (
                $objReservation instanceof Reservation &&
                ($objReservation->getEndDate())->add(new DateInterval('P30D')) >= $objDateTime
            ) {
                $blnReturn = false;
            }
        }

        return $blnReturn;
    }

}
