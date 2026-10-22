<?php

namespace App\DataFixtures;

use App\Entity\PaymentMethod;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PaymentMethodFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $definitions = [
            ['code' => 'stripe', 'name' => 'Stripe', 'default' => true, 'sort' => 10],
            ['code' => 'paypal', 'name' => 'PayPal', 'default' => false, 'sort' => 20],
            ['code' => 'testbank', 'name' => 'TestBank (Sandbox)', 'default' => false, 'sort' => 30],
        ];

        foreach ($definitions as $item) {
            // Migrations already create them; keeps `doctrine:fixtures:load --append` working
            if ($manager->getRepository(PaymentMethod::class)->findOneBy(['code' => $item['code']]) !== null) {
                continue;
            }

            $method = new PaymentMethod();
            $method->setCode($item['code'])
                ->setName($item['name'])
                ->setIsActive(true)
                ->setIsDefault($item['default'])
                ->setSortOrder($item['sort']);
            $manager->persist($method);
        }

        $manager->flush();
    }
}

