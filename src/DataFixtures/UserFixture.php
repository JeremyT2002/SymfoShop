<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixture extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Admin user
        $admin = new User();
        $admin->setEmail('admin@symfoshop.com');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $admin->setFirstName('Admin');
        $admin->setLastName('User');
        $admin->setIsActive(true);
        $manager->persist($admin);
        $this->addReference('user_admin', $admin);

        // Regular users
        $users = [
            [
                'email' => 'john.doe@example.com',
                'password' => 'user123',
                'firstName' => 'John',
                'lastName' => 'Doe',
            ],
            [
                'email' => 'jane.smith@example.com',
                'password' => 'user123',
                'firstName' => 'Jane',
                'lastName' => 'Smith',
            ],
            [
                'email' => 'bob.wilson@example.com',
                'password' => 'user123',
                'firstName' => 'Bob',
                'lastName' => 'Wilson',
            ],
        ];

        foreach ($users as $index => $userData) {
            $user = new User();
            $user->setEmail($userData['email']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $userData['password']));
            $user->setRoles(['ROLE_USER']);
            $user->setFirstName($userData['firstName']);
            $user->setLastName($userData['lastName']);
            $user->setIsActive(true);
            $manager->persist($user);
            $this->addReference('user_' . $index, $user);
        }

        $manager->flush();
    }
}

