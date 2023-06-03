<?php

namespace App\Form;

use App\Entity\Reservation;
use DateInterval;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'numberOfPersons',
                IntegerType::class,
                [
                    'attr' => [
                        'min' => 1,
                        'max' => 7,
                        'class' => ''
                    ],
                    'label' => false,
                    'data' => 1,
                ]
            )
            ->add(
                'startDate',
                DateTimeType::class,
                [
                    'attr' => [
                        'class' => ''
                    ],
                    'label' => false,
                    'data' => new \DateTime('today'),
                ]
            )
            ->add(
                'endDate',
                DateTimeType::class,
                [
                    'attr' => [
                        'class' => ''
                    ],
                    'label' => false,
                    'data' => (new \DateTime('today'))->add(new DateInterval('P3D')),
                ]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}
