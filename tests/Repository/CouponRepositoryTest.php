<?php

namespace App\Tests\Repository;

use App\Entity\Coupon;
use App\Entity\CouponType;
use App\Entity\CouponUsage;
use App\Entity\User;
use App\Repository\CouponRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CouponRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CouponRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repo = $this->em->getRepository(Coupon::class);
    }

    public function testFindByCodeIsCaseInsensitive(): void
    {
        $suffix = strtoupper(substr(uniqid('', true), -10));
        $c = new Coupon();
        $c->setCode('summer-' . strtolower($suffix));
        $c->setType(CouponType::PERCENTAGE);
        $c->setValue(25);
        $this->em->persist($c);
        $this->em->flush();

        $found = $this->repo->findByCode('  SUMMER-' . $suffix . '  ');
        $this->assertNotNull($found);
        $this->assertSame($c->getId(), $found->getId());
    }

    public function testCountUsagesSumsUsageCount(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('USAGE-' . uniqid());
        $coupon->setType(CouponType::FIXED);
        $coupon->setValue(100);
        $user = new User();
        $user->setEmail('u-' . uniqid() . '@example.com');
        $user->setPassword('x');
        $this->em->persist($coupon);
        $this->em->persist($user);
        $this->em->flush();

        $u1 = new CouponUsage();
        $u1->setCoupon($coupon);
        $u1->setUser($user);
        $u1->setUsageCount(2);
        $this->em->persist($u1);
        $this->em->flush();

        $this->assertSame(2, $this->repo->countUsages($coupon));
    }

    public function testCountUserUsages(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('PERUSER-' . uniqid());
        $coupon->setType(CouponType::PERCENTAGE);
        $coupon->setValue(10);
        $user = new User();
        $user->setEmail('pu-' . uniqid() . '@example.com');
        $user->setPassword('x');
        $this->em->persist($coupon);
        $this->em->persist($user);
        $this->em->flush();

        $usage = new CouponUsage();
        $usage->setCoupon($coupon);
        $usage->setUser($user);
        $usage->setUsageCount(3);
        $this->em->persist($usage);
        $this->em->flush();

        $this->assertSame(3, $this->repo->countUserUsages($coupon, $user));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
