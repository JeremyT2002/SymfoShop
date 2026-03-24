<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testDefaultRoleContainsRoleUser(): void
    {
        $user = new User();

        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $roles = $user->getRoles();
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testFullNameIsTrimmed(): void
    {
        $user = new User();
        $user->setFirstName('Max');
        $user->setLastName('Mustermann');

        $this->assertSame('Max Mustermann', $user->getFullName());
    }

    public function testCountryCodeIsStoredUppercase(): void
    {
        $user = new User();
        $user->setCountryCode('de');

        $this->assertSame('DE', $user->getCountryCode());
    }

    public function testFormattedAddressBuildsExpectedString(): void
    {
        $user = new User();
        $user->setCompany('ACME GmbH');
        $user->setAddressLine1('Musterstrasse 12');
        $user->setAddressLine2('2. Etage');
        $user->setPostalCode('12345');
        $user->setCity('Musterstadt');
        $user->setState('NRW');
        $user->setCountryCode('de');

        $this->assertSame(
            'ACME GmbH, Musterstrasse 12, 2. Etage, 12345 Musterstadt, NRW, DE',
            $user->getFormattedAddress()
        );
    }

    public function testFormattedAddressSkipsEmptyValues(): void
    {
        $user = new User();
        $user->setAddressLine1('Musterstrasse 12');
        $user->setCity('Musterstadt');

        $this->assertSame('Musterstrasse 12, Musterstadt', $user->getFormattedAddress());
    }

    public function testUserIdentifierAndPassword(): void
    {
        $user = new User();
        $user->setEmail('id@example.com');
        $user->setPassword('hashed');

        $this->assertSame('id@example.com', $user->getUserIdentifier());
        $this->assertSame('hashed', $user->getPassword());
        $user->eraseCredentials();
    }

    public function testCountryCodeNullAllowed(): void
    {
        $user = new User();
        $user->setCountryCode('fr');
        $user->setCountryCode(null);
        $this->assertNull($user->getCountryCode());
    }

    public function testIsResetTokenValid(): void
    {
        $user = new User();
        $this->assertFalse($user->isResetTokenValid());

        $user->setResetToken('tok');
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->assertFalse($user->isResetTokenValid());

        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 day'));
        $this->assertTrue($user->isResetTokenValid());
    }

    public function testPhoneAndLoginTimestamps(): void
    {
        $user = new User();
        $user->setPhone('+49123');
        $this->assertSame('+49123', $user->getPhone());

        $t = new \DateTimeImmutable('2024-06-01');
        $user->setLastLoginAt($t);
        $this->assertEquals($t, $user->getLastLoginAt());

        $user->setCreatedAt($t);
        $this->assertEquals($t, $user->getCreatedAt());
    }

    public function testIsActiveToggle(): void
    {
        $user = new User();
        $this->assertTrue($user->isActive());
        $user->setIsActive(false);
        $this->assertFalse($user->isActive());
    }
}


