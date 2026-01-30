<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/categories', name: 'admin_categories_')]
class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $categories = $this->categoryRepository->findBy([], ['name' => 'ASC']);
        
        // Organize categories hierarchically
        $rootCategories = array_filter($categories, fn(Category $cat) => $cat->getParent() === null);
        
        return $this->render('admin/category/index.html.twig', [
            'categories' => $categories,
            'rootCategories' => $rootCategories,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $category = new Category();
        
        if ($request->isMethod('POST')) {
            $category->setName($request->request->get('name'));
            $category->setSlug($this->slugger->slug($category->getName())->lower());
            
            $parentId = $request->request->get('parent');
            if ($parentId) {
                $parent = $this->categoryRepository->find($parentId);
                if ($parent) {
                    $category->setParent($parent);
                }
            }
            
            $this->entityManager->persist($category);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Category created successfully.');
            
            return $this->redirectToRoute('admin_categories_show', ['id' => $category->getId()]);
        }
        
        $allCategories = $this->categoryRepository->findAll();
        
        return $this->render('admin/category/new.html.twig', [
            'category' => $category,
            'allCategories' => $allCategories,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $category = $this->categoryRepository->find($id);
        
        if (!$category) {
            throw $this->createNotFoundException('Category not found');
        }
        
        return $this->render('admin/category/show.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $category = $this->categoryRepository->find($id);
        
        if (!$category) {
            throw $this->createNotFoundException('Category not found');
        }
        
        if ($request->isMethod('POST')) {
            $category->setName($request->request->get('name'));
            $category->setSlug($this->slugger->slug($category->getName())->lower());
            
            $parentId = $request->request->get('parent');
            if ($parentId && $parentId != $category->getId()) {
                $parent = $this->categoryRepository->find($parentId);
                if ($parent) {
                    $category->setParent($parent);
                }
            } else {
                $category->setParent(null);
            }
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Category updated successfully.');
            
            return $this->redirectToRoute('admin_categories_show', ['id' => $category->getId()]);
        }
        
        $allCategories = $this->categoryRepository->findAll();
        
        return $this->render('admin/category/edit.html.twig', [
            'category' => $category,
            'allCategories' => $allCategories,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): Response
    {
        $category = $this->categoryRepository->find($id);
        
        if (!$category) {
            throw $this->createNotFoundException('Category not found');
        }
        
        if ($this->isCsrfTokenValid('delete_category_' . $category->getId(), $request->request->get('_token'))) {
            // Check if category has children
            if ($category->getChildren()->count() > 0) {
                $this->addFlash('error', 'Cannot delete category with subcategories. Please remove or reassign subcategories first.');
                return $this->redirectToRoute('admin_categories_show', ['id' => $category->getId()]);
            }
            
            $this->entityManager->remove($category);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Category deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }
        
        return $this->redirectToRoute('admin_categories_index');
    }
}

