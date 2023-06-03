<?php

namespace App\DataFixtures;

use App\Entity\Car;
use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        //*** Add Categories.
        $category2 = new Category();
        $category2->setName('2-person jeep');
        $category2->setNumberOfPersons('2');
        $manager->persist($category2);

        $category5 = new Category();
        $category5->setName('5-person sedan');
        $category5->setNumberOfPersons('5');
        $manager->persist($category5);

        $category7 = new Category();
        $category7->setName('7-person minivan');
        $category7->setNumberOfPersons('7');
        $manager->persist($category7);

        $manager->flush();

        //*** Add References.
        $this->addReference('category2', $category2);
        $this->addReference('category5', $category5);
        $this->addReference('category7', $category7);

        //*** Add Cars.
        $car = new Car();
        $car->setBrand('Jeep');
        $car->setType('Wrangler');
        $car->setPrice(4999);
        $car->setCategory($this->getReference('category2'));
        $manager->persist($car);

        $car = new Car();
        $car->setBrand('Nissan');
        $car->setType('Qashqai');
        $car->setPrice(5499);
        $car->setCategory($this->getReference('category5'));
        $manager->persist($car);

        $car = new Car();
        $car->setBrand('Kia');
        $car->setType('Sorento');
        $car->setPrice(9154);
        $car->setCategory($this->getReference('category7'));
        $manager->persist($car);

        $manager->flush();
    }
}
