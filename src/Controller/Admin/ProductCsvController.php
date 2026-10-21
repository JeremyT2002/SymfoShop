<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Catalog\ProductCsv;
use App\Service\Catalog\ProductImportResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/products', name: 'admin_products_')]
#[IsGranted('ROLE_ADMIN')]
final class ProductCsvController extends AbstractController
{
    private const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private readonly ProductCsv $productCsv,
    ) {
    }

    #[Route('/export.csv', name: 'export', methods: ['GET'])]
    public function export(): StreamedResponse
    {
        $response = new StreamedResponse(function (): void {
            $out = fopen('php://output', 'wb');
            $this->productCsv->export($out);
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="products-%s.csv"', date('Y-m-d')));

        return $response;
    }

    #[Route('/import', name: 'import', methods: ['GET', 'POST'])]
    public function import(Request $request): Response
    {
        $result = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('product_import', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $file = $request->files->get('file');
            if (!$file instanceof UploadedFile || !$file->isValid() || $file->getSize() > self::MAX_UPLOAD_BYTES) {
                $result = new ProductImportResult(true);
                $result->addError(0, 'Please choose a CSV file of at most 10 MB.');
            } else {
                $result = $this->productCsv->import($file->getPathname(), dryRun: !$request->request->getBoolean('apply'));
            }
        }

        return $this->render('admin/product/import.html.twig', [
            'result' => $result,
            'columns' => ProductCsv::COLUMNS,
        ]);
    }
}
