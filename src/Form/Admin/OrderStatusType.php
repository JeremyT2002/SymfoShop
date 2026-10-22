<?php

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderStatusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('status', ChoiceType::class, [
            'choices' => OrderFilterType::statusChoices(),
            'choice_translation_domain' => 'messages',
            'required' => true,
        ]);
        $builder->add('carrier', TextType::class, [
            'label' => 'ui.order_status.carrier',
            'required' => false,
            'attr' => ['maxlength' => 100, 'placeholder' => 'DHL, DPD, UPS, Hermes, GLS …', 'list' => 'carrier-suggestions'],
        ]);
        $builder->add('trackingNumber', TextType::class, [
            'label' => 'ui.order_status.tracking',
            'required' => false,
            'attr' => ['maxlength' => 255],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'update_status',
            'translation_domain' => 'messages',
        ]);
    }
}

