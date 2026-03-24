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

        // Regular users (first user has a full profile for checkout / account dashboard demos)
        $users = [
            [
                'email' => 'john.doe@example.com',
                'password' => 'user123',
                'firstName' => 'John',
                'lastName' => 'Doe',
                'phone' => '+49 30 12345678',
                'addressLine1' => 'Musterstraße 1',
                'addressLine2' => null,
                'postalCode' => '10115',
                'city' => 'Berlin',
                'state' => null,
                'countryCode' => 'DE',
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
            if (isset($userData['phone'])) {
                $user->setPhone($userData['phone']);
            }
            if (isset($userData['addressLine1'])) {
                $user->setAddressLine1($userData['addressLine1']);
            }
            if (isset($userData['addressLine2'])) {
                $user->setAddressLine2($userData['addressLine2']);
            }
            if (isset($userData['postalCode'])) {
                $user->setPostalCode($userData['postalCode']);
            }
            if (isset($userData['city'])) {
                $user->setCity($userData['city']);
            }
            if (isset($userData['state'])) {
                $user->setState($userData['state']);
            }
            if (isset($userData['countryCode'])) {
                $user->setCountryCode($userData['countryCode']);
            }
            $manager->persist($user);
            $this->addReference('user_' . $index, $user);
        }

        $manager->flush();
    }
}

