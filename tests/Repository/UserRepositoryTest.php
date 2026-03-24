<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class UserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repo = $this->em->getRepository(User::class);
    }

    public function testUpgradePasswordRejectsNonAppUser(): void
    {
        /** @var PasswordAuthenticatedUserInterface&MockObject $foreign */
        $foreign = $this->createMock(PasswordAuthenticatedUserInterface::class);

        $this->expectException(UnsupportedUserException::class);
        $this->repo->upgradePassword($foreign, 'hashed');
    }

    public function testUpgradePasswordUpdatesUser(): void
    {
        $user = new User();
        $user->setEmail('upg-' . uniqid() . '@example.com');
        $user->setPassword('old');
        $this->em->persist($user);
        $this->em->flush();

        $this->repo->upgradePassword($user, 'new-hash');
        $this->em->refresh($user);

        $this->assertSame('new-hash', $user->getPassword());
    }

    public function testFindOneByEmailForAuthRespectsIsActive(): void
    {
        $active = new User();
        $active->setEmail('act-' . uniqid() . '@example.com');
        $active->setPassword('p');
        $active->setIsActive(true);

        $inactive = new User();
        $inactive->setEmail('inact-' . uniqid() . '@example.com');
        $inactive->setPassword('p');
        $inactive->setIsActive(false);

        $this->em->persist($active);
        $this->em->persist($inactive);
        $this->em->flush();

        $this->assertNotNull($this->repo->findOneByEmailForAuth($active->getEmail()));
        $this->assertNull($this->repo->findOneByEmailForAuth($inactive->getEmail()));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
