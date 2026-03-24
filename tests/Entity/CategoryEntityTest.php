<?php

namespace App\Tests\Entity;

use App\Entity\Category;
use PHPUnit\Framework\TestCase;

final class CategoryEntityTest extends TestCase
{
    public function testParentChildRelations(): void
    {
        $root = new Category();
        $root->setName('Root');
        $root->setSlug('root');
        $child = new Category();
        $child->setName('Child');
        $child->setSlug('child');

        $root->addChild($child);
        $this->assertSame($root, $child->getParent());
        $this->assertTrue($root->getChildren()->contains($child));

        $root->removeChild($child);
        $this->assertNull($child->getParent());
        $this->assertFalse($root->getChildren()->contains($child));
    }
}
