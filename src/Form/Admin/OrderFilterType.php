<?php

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderFilterType extends AbstractType
{
    public const STATUSES = [
        'new',
        'payment_pending',
        'payment_confirmed',
        'paid',
        'processing',
        'shipped',
        'completed',
        'cancelled',
    ];

    public static function statusChoices(): array
    {
        $choices = [];
        foreach (self::STATUSES as $status) {
            $choices['admin.orders.status.' . $status] = $status;
        }

        return $choices;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('search', SearchType::class, [
                'required' => false,
                'empty_data' => '',
            ])
            ->add('status', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'admin.orders.all_statuses',
                'choices' => self::statusChoices(),
                'choice_translation_domain' => 'messages',
                'empty_data' => '',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'method' => 'GET',
            'translation_domain' => 'messages',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}

