<?php

namespace App\Form\Checkout;

use App\DTO\Checkout\AddressDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('street', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Street address is required']),
                ],
                'attr' => [
                    'placeholder' => '123 Main Street',
                ],
            ])
            ->add('city', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'City is required']),
                ],
                'attr' => [
                    'placeholder' => 'New York',
                ],
            ])
            ->add('postalCode', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Postal code is required']),
                ],
                'attr' => [
                    'placeholder' => '10001',
                ],
            ])
            ->add('country', CountryType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Country is required']),
                ],
                'preferred_choices' => ['US', 'GB', 'DE', 'FR', 'ES', 'IT'],
            ])
            ->add('state', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'NY (optional)',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AddressDTO::class,
        ]);
    }
}

