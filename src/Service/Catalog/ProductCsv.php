<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Entity\StockItem;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Products as CSV, one row per variant (SKU). Export and import use the same columns,
 * so an exported file can be edited in a spreadsheet and imported again.
 *
 * Import rules: rows are matched by SKU (update) or create a new variant; the product is found
 * by product_slug. Empty product columns leave the value unchanged. All rows are validated first –
 * a file with errors changes nothing.
 */
final class ProductCsv
{
    public const COLUMNS = [
        'sku', 'product_slug', 'product_name', 'status', 'category', 'tax_class',
        'price', 'compare_at_price', 'currency', 'stock', 'attributes', 'description',
    ];

    private const TAX_CLASSES = ['standard', 'reduced', 'zero', 'exempt'];

    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductVariantRepository $variants,
        private readonly CategoryRepository $categories,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * Writes all products with their variants to $stream (UTF-8 with BOM, so Excel detects the encoding).
     *
     * @param resource $stream
     */
    public function export($stream): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::COLUMNS, escape: '');

        foreach ($this->products->findBy([], ['id' => 'ASC']) as $product) {
            foreach ($product->getVariants() as $variant) {
                fputcsv($stream, [
                    $variant->getSku(),
                    $product->getSlug(),
                    $product->getName(),
                    $product->getStatus()->value,
                    $product->getCategory()?->getSlug() ?? '',
                    $product->getTaxClass(),
                    self::formatPrice($variant->getPriceAmount()),
                    $variant->getCompareAtPriceAmount() !== null ? self::formatPrice($variant->getCompareAtPriceAmount()) : '',
                    $variant->getCurrency(),
                    (string) ($variant->getStockItem()?->getOnHand() ?? 0),
                    self::formatAttributes($variant->getAttributes()),
                    (string) $product->getDescription(),
                ], escape: '');
            }
        }
    }

    public function import(string $path, bool $dryRun = false): ProductImportResult
    {
        $result = new ProductImportResult($dryRun);
        $rows = $this->readRows($path, $result);
        if ($result->hasErrors()) {
            return $result;
        }

        $this->entityManager->beginTransaction();
        try {
            /** @var array<string, Product> $productsBySlug */
            $productsBySlug = [];
            /** @var array<string, true> $seenSkus */
            $seenSkus = [];

            foreach ($rows as $line => $row) {
                $this->importRow($line, $row, $productsBySlug, $seenSkus, $result);
            }

            if ($result->hasErrors() || $dryRun) {
                $this->entityManager->rollback();
                $this->entityManager->clear();

                return $result;
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $e) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            $this->entityManager->clear();
            throw $e;
        }

        return $result;
    }

    /**
     * @param array<string, string> $row
     * @param array<string, Product> $productsBySlug
     * @param array<string, true> $seenSkus
     */
    private function importRow(int $line, array $row, array &$productsBySlug, array &$seenSkus, ProductImportResult $result): void
    {
        $sku = $row['sku'];
        if ($sku === '') {
            $result->addError($line, 'SKU is missing.');

            return;
        }
        if (isset($seenSkus[$sku])) {
            $result->addError($line, sprintf('SKU "%s" appears more than once.', $sku));

            return;
        }
        $seenSkus[$sku] = true;

        $variant = $this->variants->findOneBy(['sku' => $sku]);
        $isNewVariant = $variant === null;

        // Product: by slug, else the product of the existing variant, else a new one
        $slug = $row['product_slug'] !== '' ? (string) $this->slugger->slug($row['product_slug'])->lower() : '';
        $product = $slug !== '' ? ($productsBySlug[$slug] ?? $this->products->findOneBySlug($slug)) : $variant?->getProduct();

        if ($variant !== null && $product !== null && $variant->getProduct() !== $product) {
            $result->addError($line, sprintf('SKU "%s" belongs to product "%s".', $sku, $variant->getProduct()->getSlug()));

            return;
        }

        if ($product === null) {
            if ($row['product_name'] === '') {
                $result->addError($line, 'product_name is required for a new product.');

                return;
            }
            $slug = $slug !== '' ? $slug : (string) $this->slugger->slug($row['product_name'])->lower();
            if (isset($productsBySlug[$slug]) || $this->products->findOneBySlug($slug) !== null) {
                $product = $productsBySlug[$slug] ?? $this->products->findOneBySlug($slug);
            } else {
                $product = (new Product())
                    ->setName($row['product_name'])
                    ->setSlug($slug)
                    ->setStatus(ProductStatus::DRAFT)
                    ->setTaxClass('standard');
                $this->entityManager->persist($product);
                $result->productsCreated++;
            }
        }
        $productsBySlug[$product->getSlug()] = $product;

        if (!$this->applyProductColumns($line, $row, $product, $result)) {
            return;
        }

        // Variant
        $price = self::parsePrice($row['price']);
        $compareAt = self::parsePrice($row['compare_at_price']);
        $stock = $row['stock'] === '' ? null : filter_var($row['stock'], \FILTER_VALIDATE_INT);

        if ($row['price'] !== '' && $price === null) {
            $result->addError($line, sprintf('Invalid price "%s".', $row['price']));

            return;
        }
        if ($row['compare_at_price'] !== '' && $compareAt === null) {
            $result->addError($line, sprintf('Invalid compare_at_price "%s".', $row['compare_at_price']));

            return;
        }
        if ($stock === false || ($stock !== null && $stock < 0)) {
            $result->addError($line, sprintf('Invalid stock "%s".', $row['stock']));

            return;
        }
        if ($isNewVariant && $price === null) {
            $result->addError($line, 'price is required for a new SKU.');

            return;
        }

        if ($isNewVariant) {
            $variant = (new ProductVariant())->setSku($sku)->setProduct($product);
            $product->addVariant($variant);
            $this->entityManager->persist($variant);
            $result->variantsCreated++;
        } else {
            $result->variantsUpdated++;
        }

        if ($price !== null) {
            $variant->setPriceAmount($price);
        }
        if ($row['compare_at_price'] !== '' || $isNewVariant) {
            $variant->setCompareAtPriceAmount($compareAt);
        }
        if ($row['currency'] !== '') {
            $variant->setCurrency(strtoupper($row['currency']));
        }
        if ($row['attributes'] !== '') {
            $variant->setAttributes(self::parseAttributes($row['attributes']));
        }
        $variant->setUpdatedAt(new \DateTimeImmutable());

        if ($stock !== null || $isNewVariant) {
            $stockItem = $variant->getStockItem();
            if ($stockItem === null) {
                $stockItem = (new StockItem())->setVariant($variant)->setOnHand(0)->setReserved(0);
                $variant->setStockItem($stockItem);
                $this->entityManager->persist($stockItem);
            }
            $stockItem->setOnHand($stock ?? 0);
        }
    }

    /**
     * @param array<string, string> $row
     */
    private function applyProductColumns(int $line, array $row, Product $product, ProductImportResult $result): bool
    {
        if ($row['status'] !== '') {
            $status = ProductStatus::tryFrom(strtolower($row['status']));
            if ($status === null) {
                $result->addError($line, sprintf('Unknown status "%s" (active, draft, archived).', $row['status']));

                return false;
            }
            $product->setStatus($status);
        }

        if ($row['tax_class'] !== '') {
            if (!\in_array(strtolower($row['tax_class']), self::TAX_CLASSES, true)) {
                $result->addError($line, sprintf('Unknown tax_class "%s" (%s).', $row['tax_class'], implode(', ', self::TAX_CLASSES)));

                return false;
            }
            $product->setTaxClass(strtolower($row['tax_class']));
        }

        if ($row['category'] !== '') {
            $category = $this->categories->findOneBySlug($row['category']);
            if (!$category instanceof Category) {
                $result->addError($line, sprintf('Unknown category "%s" (use the category slug).', $row['category']));

                return false;
            }
            $product->setCategory($category);
        }

        if ($row['product_name'] !== '') {
            $product->setName($row['product_name']);
        }
        if ($row['description'] !== '') {
            $product->setDescription($row['description']);
        }
        $product->setUpdatedAt(new \DateTimeImmutable());

        return true;
    }

    /**
     * @return array<int, array<string, string>> rows by line number
     */
    private function readRows(string $path, ProductImportResult $result): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $result->addError(0, 'The file could not be read.');

            return [];
        }

        try {
            $firstLine = (string) fgets($handle);
            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;
            // Excel in German locales saves with semicolons
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $header = array_map(static fn (?string $h): string => strtolower(trim((string) $h)), str_getcsv($firstLine, $delimiter, '"', ''));

            $missing = array_diff(['sku'], $header);
            $unknown = array_diff($header, self::COLUMNS);
            if ($missing !== [] || $unknown !== []) {
                $result->addError(1, sprintf(
                    'Invalid header. Expected columns: %s.%s',
                    implode(', ', self::COLUMNS),
                    $unknown !== [] ? ' Unknown: ' . implode(', ', $unknown) . '.' : ''
                ));

                return [];
            }

            $rows = [];
            $line = 1;
            while (($fields = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                ++$line;
                if ($fields === [null] || implode('', $fields) === '') {
                    continue;
                }
                $row = array_fill_keys(self::COLUMNS, '');
                foreach ($header as $i => $column) {
                    $row[$column] = trim((string) ($fields[$i] ?? ''));
                }
                $rows[$line] = $row;
            }

            if ($rows === []) {
                $result->addError(0, 'The file contains no rows.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** "19.99", "19,99", "1.299,00" → minor units; null when empty or invalid. */
    public static function parsePrice(string $value): ?int
    {
        $value = str_replace([' ', "\u{00A0}", '€'], '', $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{1,2}$/', $value) === 1) {
            $value = str_replace('.', '', $value);
        }
        $value = str_replace(',', '.', $value);
        if (preg_match('/^\d+(\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }

        return (int) round((float) $value * 100);
    }

    private static function formatPrice(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, '.', '');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function formatAttributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $key => $value) {
            if (\is_scalar($value)) {
                $parts[] = $key . '=' . $value;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * "size=M; color=Blue" → ['size' => 'M', 'color' => 'Blue']
     *
     * @return array<string, string>
     */
    private static function parseAttributes(string $value): array
    {
        $attributes = [];
        foreach (explode(';', $value) as $pair) {
            [$key, $val] = array_map('trim', explode('=', $pair, 2)) + [1 => ''];
            if ($key !== '' && $val !== '') {
                $attributes[$key] = $val;
            }
        }

        return $attributes;
    }
}
