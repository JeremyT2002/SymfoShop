<?php

namespace App\Controller\Admin;

use App\Entity\ApiKey;
use App\Entity\AuditLog;
use App\Entity\Category;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Entity\ProductVariant;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('SymfoShop Admin')
            ->setFaviconPath('favicon.ico');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('Catalog');
        yield MenuItem::linkToCrud('Products', 'fa fa-box', Product::class);
        yield MenuItem::linkToCrud('Product Variants', 'fa fa-tags', ProductVariant::class);
        yield MenuItem::linkToCrud('Categories', 'fa fa-folder', Category::class);
        yield MenuItem::linkToCrud('Product Media', 'fa fa-image', ProductMedia::class);
        yield MenuItem::section('Orders');
        yield MenuItem::linkToCrud('Orders', 'fa fa-shopping-cart', Order::class);
        yield MenuItem::section('System');
        yield MenuItem::linkToCrud('Users', 'fa fa-users', User::class);
        yield MenuItem::linkToCrud('API Keys', 'fa fa-key', ApiKey::class);
        yield MenuItem::linkToCrud('Audit Logs', 'fa fa-history', AuditLog::class);
    }
}

