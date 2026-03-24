<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ReturnRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ReturnRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('orderNumber', TextType::class, [
                'label' => 'return_request.form.order_number',
                'attr' => ['class' => 'w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'return_request.form.email',
                'attr' => ['class' => 'w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100'],
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'return_request.form.reason',
                'attr' => [
                    'rows' => 6,
                    'class' => 'w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReturnRequest::class,
            'csrf_token_id' => 'return_request',
            'translation_domain' => 'messages',
        ]);
    }
}
