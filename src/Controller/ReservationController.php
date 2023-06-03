<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\ReservationFormType;
use DateInterval;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class ReservationController extends AbstractController
{
    public function __construct(
        protected EntityManagerInterface $em,
        protected RequestStack $requestStack,
    ) {}

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    #[Route('/reservation/create', name: 'reservation_create')]
    public function create(Request $request, UserInterface $user): Response
    {
        $objSession = $this->requestStack->getSession();
        $objReservation = new Reservation();
        $objForm = $this->createForm(ReservationFormType::class, $objReservation);
        $objForm->handleRequest($request);

        if ($objForm->isSubmitted()) {
            /** @var Reservation $objReservation */
            $objReservation = $objForm->getData();

            if (!$this->canUserMakeReservation($user, $objReservation->getStartDate())) {
                $objForm->addError(new FormError('You are limited to rente a car once every 30 days.'));
            }
                if ($user instanceof User) {
                    $objReservation->setUser($user);
                }

                $objReservation = $this->tryToFindCar($objReservation);

                if (!$objReservation->getCar() instanceof Car) {
                    $objForm->addError(new FormError('No car found for this period.'));
                }

            if ($objForm->isValid()) {
                $objSession->set('reservation', $objReservation);
                $objSession->set('car_id', $objReservation->getCar()->getId());

                return $this->redirectToRoute('reservation.show');
            }
        }

        return $this->render('reservation/index.html.twig', [
            'form' => $objForm->createView(),
        ]);
    }

    #[Route('/reservation/show', name: 'reservation.show')]
    public function show(Request $request, UserInterface $user): Response
    {
        $objSession = $this->requestStack->getSession();
        $objReservation = $objSession->get('reservation');
        $intCarId = $objSession->get('car_id');
        
        if ($objReservation instanceof Reservation && !is_null($intCarId)) {
            $objCar = $this->em->getRepository(Car::class)->Find($intCarId);

            return $this->render('reservation/show.html.twig', [
                'reservation' => $objReservation,
                'car' => $objCar,
            ]);
        }

        return $this->redirectToRoute('dashboard.index');
    }

    #[Route('/reservation/store', name: 'reservation.store')]
    public function store(Request $request, UserInterface $objUser): Response
    {
        $objSession = $this->requestStack->getSession();
        $objReservation = $objSession->get('reservation');
        $objCar = $this->em->getRepository(Car::class)->Find($objSession->get('car_id'));

        if ($objReservation instanceof Reservation && $objCar instanceof Car && $objUser instanceof User) {
            $objReservation->setUser($objUser);
            $objReservation->setCar($objCar);

            if ($request->get('accept') === 'yes') {
                $this->em->persist($objReservation);
                $this->em->flush();
            }

            $objSession->clear();
        }

        return $this->redirectToRoute('dashboard.index');
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

    /**
     * Create a book with the Car the fit the needs.
     *
     * @param Reservation $reservation
     * @return Reservation
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function tryToFindCar(Reservation $reservation): Reservation
    {
        //*** Find car ids that are NOT available for this period.
        $arrIds = $this->getCarIdsThatHaveReservations($reservation);

        //*** Find Car that fits
        $qb = $this->em->createQueryBuilder();
        $qb->select('car')
            ->from(Car::class, 'car')
            ->leftJoin('car.category', 'cat');

        if (count($arrIds) > 0) {
            $qb->where($qb->expr()->notIn('car.id', ':ids'))
                ->setParameter('ids', $arrIds);
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

    /**
     * Checks if given user can make a reservation, given the 30 days fair usage.
     *
     * @param UserInterface $user
     * @param DateTimeInterface|null $objDateTime
     * @return bool
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
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

    /**
     * Get Car Ids that have reservations for given period.
     *
     * @param Reservation $reservation
     * @return array
     */
    protected function getCarIdsThatHaveReservations(Reservation $reservation): array
    {
        $query = $this->em->createQuery(
            'select DISTINCT IDENTITY(r.car) from App\Entity\Reservation r
                where r.startDate < :end
                and r.endDate > :start'
        )
            ->setParameters(['start' => $reservation->getStartDate(), 'end' => $reservation->getEndDate()]);

        $result = $query->getScalarResult();

        return array_column($result, "1");
    }
}
