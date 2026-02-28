<?php

namespace App\Theme;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class ThemeConfig extends Constraint
{
    public string $message = 'Invalid theme configuration.';
}
