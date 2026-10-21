<?php

declare(strict_types=1);

namespace App\Service\Catalog;

final class ProductImportResult
{
    public int $productsCreated = 0;
    public int $variantsCreated = 0;
    public int $variantsUpdated = 0;

    /** @var list<array{line: int, message: string}> */
    private array $errors = [];

    public function __construct(
        public readonly bool $dryRun,
    ) {
    }

    public function addError(int $line, string $message): void
    {
        $this->errors[] = ['line' => $line, 'message' => $message];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return list<array{line: int, message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** Whether the changes were written to the database. */
    public function isApplied(): bool
    {
        return !$this->dryRun && !$this->hasErrors();
    }
}
