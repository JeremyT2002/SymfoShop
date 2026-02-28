<?php

namespace App\Theme;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates theme configuration JSON structure.
 */
class ThemeConfigValidator extends ConstraintValidator
{
    private const ALLOWED_TOP_LEVEL_KEYS = ['meta', 'colors', 'typography', 'layout', 'components', 'customCss'];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ThemeConfig) {
            throw new UnexpectedTypeException($constraint, ThemeConfig::class);
        }

        if ($value === null || $value === []) {
            return;
        }

        if (!is_array($value)) {
            $this->context->buildViolation('Theme config must be an array.')
                ->addViolation();
            return;
        }

        foreach (array_keys($value) as $key) {
            if (!in_array($key, self::ALLOWED_TOP_LEVEL_KEYS, true)) {
                $this->context->buildViolation('Unknown theme config key "{{ key }}". Allowed: {{ allowed }}.')
                    ->setParameter('{{ key }}', $key)
                    ->setParameter('{{ allowed }}', implode(', ', self::ALLOWED_TOP_LEVEL_KEYS))
                    ->addViolation();
            }
        }

        if (isset($value['customCss']) && !is_string($value['customCss'])) {
            $this->context->buildViolation('customCss must be a string.')
                ->atPath('customCss')
                ->addViolation();
        }
    }
}
