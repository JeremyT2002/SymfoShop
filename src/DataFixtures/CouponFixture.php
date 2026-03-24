<?php

namespace App\DataFixtures;

use App\Entity\Coupon;
use App\Entity\CouponType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CouponFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $welcome = new Coupon();
        $welcome->setCode('WELCOME10');
        $welcome->setType(CouponType::PERCENTAGE);
        $welcome->setValue(10);
        $welcome->setExpiresAt(new \DateTimeImmutable('+1 year'));
        $welcome->setUsageLimit(1000);
        $welcome->setPerUserLimit(1);
        $welcome->setIsActive(true);
        $manager->persist($welcome);

        $fixed = new Coupon();
        $fixed->setCode('SAVE500');
        $fixed->setType(CouponType::FIXED);
        $fixed->setValue(500);
        $fixed->setExpiresAt(new \DateTimeImmutable('+6 months'));
        $fixed->setUsageLimit(500);
        $fixed->setPerUserLimit(3);
        $fixed->setIsActive(true);
        $manager->persist($fixed);

        $manager->flush();
    }
}
