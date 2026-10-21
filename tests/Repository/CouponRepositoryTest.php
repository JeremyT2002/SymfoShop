<?php

namespace App\Tests\Repository;

use App\Entity\Coupon;
use App\Entity\CouponType;
use App\Entity\CouponUsage;
use App\Entity\Order;
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

    public function testCountUsagesCountsPaidOrdersIncludingGuests(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('USAGE-' . uniqid());
        $coupon->setType(CouponType::FIXED);
        $coupon->setValue(100);
        $this->em->persist($coupon);

        foreach (['paid', 'shipped', 'new', 'cancelled'] as $status) {
            $order = new Order();
            $order->setOrderNumber('ORD-CPN-' . $status . '-' . uniqid());
            $order->setEmail('guest-' . uniqid() . '@example.com');
            $order->setCurrency('EUR');
            $order->setStatus($status);
            $order->setSubtotal(1000);
            $order->setTaxTotal(0);
            $order->setGrandTotal(900);
            $order->setCouponCode(strtolower($coupon->getCode()));
            $order->setDiscountTotal(100);
            $this->em->persist($order);
        }
        $this->em->flush();

        // Only redeemed (paid or later) orders count; unpaid and cancelled ones do not.
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
