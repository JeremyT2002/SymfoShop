<?php

namespace App\Tests\Repository;

use App\Entity\Coupon;
use App\Entity\CouponType;
use App\Entity\CouponUsage;
use App\Entity\User;
use App\Repository\CouponUsageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CouponUsageRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CouponUsageRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repo = static::getContainer()->get(CouponUsageRepository::class);
    }

    public function testFindOrCreateCreatesNew(): void
    {
        $coupon = $this->persistCoupon();
        $user = $this->persistUser();

        $usage = $this->repo->findOrCreate($coupon, $user);
        $this->em->flush();

        $this->assertInstanceOf(CouponUsage::class, $usage);
        $this->assertSame(1, $usage->getUsageCount());
    }

    public function testFindOrCreateReturnsExisting(): void
    {
        $coupon = $this->persistCoupon();
        $user = $this->persistUser();

        $a = $this->repo->findOrCreate($coupon, $user);
        $this->em->flush();
        $b = $this->repo->findOrCreate($coupon, $user);

        $this->assertSame($a->getId(), $b->getId());
    }

    public function testIncrementUsage(): void
    {
        $coupon = $this->persistCoupon();
        $user = $this->persistUser();

        $this->repo->incrementUsage($coupon, $user);
        $this->repo->incrementUsage($coupon, $user);

        $usage = $this->repo->findOneBy(['coupon' => $coupon, 'user' => $user]);
        $this->assertNotNull($usage);
        $this->assertSame(3, $usage->getUsageCount());
    }

    private function persistCoupon(): Coupon
    {
        $c = new Coupon();
        $c->setCode('CU-' . uniqid());
        $c->setType(CouponType::PERCENTAGE);
        $c->setValue(5);
        $this->em->persist($c);
        $this->em->flush();

        return $c;
    }

    private function persistUser(): User
    {
        $u = new User();
        $u->setEmail('cu-user-' . uniqid() . '@example.com');
        $u->setPassword('p');
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
