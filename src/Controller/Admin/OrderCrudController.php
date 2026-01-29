<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;
use Doctrine\ORM\EntityManagerInterface;

class OrderCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    private function getOrderWorkflow(): WorkflowInterface
    {
        return $this->container->get('workflow.order');
    }

    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Order')
            ->setEntityLabelInPlural('Orders')
            ->setPageTitle('index', 'Orders')
            ->setPageTitle('new', 'Create Order')
            ->setPageTitle('edit', 'Edit Order')
            ->setPageTitle('detail', 'Order Details')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $submitPayment = Action::new('submitPayment', 'Submit Payment')
            ->linkToRoute('admin_order_transition', function (Order $order) {
                return ['id' => $order->getId(), 'transition' => 'submit_payment'];
            })
            ->displayIf(fn(Order $order) => $this->getOrderWorkflow()->can($order, 'submit_payment'));

        $confirmPayment = Action::new('confirmPayment', 'Confirm Payment')
            ->linkToRoute('admin_order_transition', function (Order $order) {
                return ['id' => $order->getId(), 'transition' => 'confirm_payment'];
            })
            ->displayIf(fn(Order $order) => $this->getOrderWorkflow()->can($order, 'confirm_payment'));

        $startProcessing = Action::new('startProcessing', 'Start Processing')
            ->linkToRoute('admin_order_transition', function (Order $order) {
                return ['id' => $order->getId(), 'transition' => 'start_processing'];
            })
            ->displayIf(fn(Order $order) => $this->getOrderWorkflow()->can($order, 'start_processing'));

        $ship = Action::new('ship', 'Ship Order')
            ->linkToRoute('admin_order_transition', function (Order $order) {
                return ['id' => $order->getId(), 'transition' => 'ship'];
            })
            ->displayIf(fn(Order $order) => $this->getOrderWorkflow()->can($order, 'ship'));

        $complete = Action::new('complete', 'Complete Order')
            ->linkToRoute('admin_order_transition', function (Order $order) {
                return ['id' => $order->getId(), 'transition' => 'complete'];
            })
            ->displayIf(fn(Order $order) => $this->getOrderWorkflow()->can($order, 'complete'));

        $cancel = Action::new('cancel', 'Cancel Order')
            ->linkToRoute('admin_order_transition', function (Order $order) {
                return ['id' => $order->getId(), 'transition' => 'cancel'];
            })
            ->displayIf(fn(Order $order) => $this->getOrderWorkflow()->can($order, 'cancel'))
            ->setCssClass('btn btn-danger');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $submitPayment)
            ->add(Crud::PAGE_INDEX, $confirmPayment)
            ->add(Crud::PAGE_INDEX, $startProcessing)
            ->add(Crud::PAGE_INDEX, $ship)
            ->add(Crud::PAGE_INDEX, $complete)
            ->add(Crud::PAGE_INDEX, $cancel)
            ->add(Crud::PAGE_DETAIL, $submitPayment)
            ->add(Crud::PAGE_DETAIL, $confirmPayment)
            ->add(Crud::PAGE_DETAIL, $startProcessing)
            ->add(Crud::PAGE_DETAIL, $ship)
            ->add(Crud::PAGE_DETAIL, $complete)
            ->add(Crud::PAGE_DETAIL, $cancel);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('status')
            ->add('email')
            ->add('orderNumber')
            ->add('createdAt');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();

        yield TextField::new('orderNumber')
            ->setColumns('col-md-6')
            ->setRequired(true);

        yield TextField::new('email')
            ->setColumns('col-md-6')
            ->setRequired(true);

        yield ChoiceField::new('status')
            ->setChoices([
                'New' => 'new',
                'Payment Pending' => 'payment_pending',
                'Paid' => 'paid',
                'Processing' => 'processing',
                'Shipped' => 'shipped',
                'Completed' => 'completed',
                'Cancelled' => 'cancelled',
            ])
            ->setColumns('col-md-4')
            ->setRequired(true);

        yield TextField::new('currency')
            ->setColumns('col-md-2')
            ->setRequired(true);

        yield MoneyField::new('subtotal', 'Subtotal')
            ->setCurrencyPropertyPath('currency')
            ->setStoredAsCents(true)
            ->setColumns('col-md-2')
            ->onlyOnDetail();

        yield MoneyField::new('taxTotal', 'Tax Total')
            ->setCurrencyPropertyPath('currency')
            ->setStoredAsCents(true)
            ->setColumns('col-md-2')
            ->onlyOnDetail();

        yield MoneyField::new('grandTotal', 'Grand Total')
            ->setCurrencyPropertyPath('currency')
            ->setStoredAsCents(true)
            ->setColumns('col-md-2')
            ->setRequired(true);

        yield AssociationField::new('items')
            ->setColumns('col-md-12')
            ->onlyOnDetail();

        yield AssociationField::new('payments', 'Payments')
            ->setColumns('col-md-12')
            ->onlyOnDetail()
            ->formatValue(function ($value, $entity) {
                if (!$entity->getPayments()->isEmpty()) {
                    $payments = [];
                    foreach ($entity->getPayments() as $payment) {
                        $payments[] = sprintf(
                            '%s - %s (%s) - %s %s',
                            $payment->getPaymentIntentId(),
                            $payment->getStatus(),
                            $payment->getProvider(),
                            number_format($payment->getAmount() / 100, 2),
                            $payment->getCurrency()
                        );
                    }
                    return implode('<br>', $payments);
                }
                return 'No payments';
            });

        yield TextField::new('trackingNumber', 'Tracking Number')
            ->setColumns('col-md-6')
            ->hideOnIndex();

        yield TextField::new('carrier', 'Carrier')
            ->setColumns('col-md-6')
            ->hideOnIndex();

        yield DateTimeField::new('shippedAt', 'Shipped At')
            ->setColumns('col-md-6')
            ->hideOnIndex()
            ->setFormat('yyyy-MM-dd HH:mm:ss');

        yield DateTimeField::new('createdAt')
            ->setColumns('col-md-6')
            ->onlyOnDetail()
            ->setFormat('yyyy-MM-dd HH:mm:ss');
    }

    #[Route('/admin/order/{id}/transition/{transition}', name: 'admin_order_transition', methods: ['POST'])]
    public function transition(int $id, string $transition): Response
    {
        $order = $this->entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        if (!$this->getOrderWorkflow()->can($order, $transition)) {
            $this->addFlash('error', 'Transition "' . $transition . '" is not available for this order.');
            return $this->redirect($this->generateUrl('admin', [
                'crudAction' => 'detail',
                'crudControllerFqcn' => self::class,
                'entityId' => $id,
            ]));
        }

        try {
            $this->getOrderWorkflow()->apply($order, $transition);
            $this->entityManager->flush();

            $this->addFlash('success', 'Order transitioned to: ' . $order->getStatus());
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error applying transition: ' . $e->getMessage());
        }

        return $this->redirect($this->generateUrl('admin', [
            'crudAction' => 'detail',
            'crudControllerFqcn' => self::class,
            'entityId' => $id,
        ]));
    }
}

