<?php

namespace App\Form\Admin;

use App\Entity\Coupon;
use App\Entity\CouponType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CouponFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 50),
                ],
                'attr' => ['placeholder' => 'SAVE10'],
            ])
            ->add('type', EnumType::class, [
                'class' => CouponType::class,
                'choice_label' => static fn (CouponType $type) => ucfirst($type->value),
            ])
            ->add('value', IntegerType::class, [
                'constraints' => [
                    new NotBlank(),
                    new GreaterThanOrEqual(0),
                ],
                'help' => 'For percentage: 0-100. For fixed: amount in cents (e.g., 1000 = 10.00 EUR).',
            ])
            ->add('expiresAt', DateTimeType::class, [
                'required' => false,
                'input' => 'datetime_immutable',
                'widget' => 'single_text',
            ])
            ->add('usageLimit', IntegerType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [new GreaterThanOrEqual(1)],
                'help' => 'Leave empty for unlimited.',
            ])
            ->add('perUserLimit', IntegerType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [new GreaterThanOrEqual(1)],
                'help' => 'Leave empty for unlimited.',
            ])
            ->add('isActive', CheckboxType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Coupon::class,
        ]);
    }
}

