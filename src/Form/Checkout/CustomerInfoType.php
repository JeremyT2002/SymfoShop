<?php

namespace App\Form\Checkout;

use App\DTO\Checkout\CustomerInfoDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class CustomerInfoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Email is required']),
                    new Email(['message' => 'Please enter a valid email address']),
                ],
                'attr' => [
                    'placeholder' => 'your@email.com',
                ],
            ])
            ->add('firstName', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'First name is required']),
                ],
                'attr' => [
                    'placeholder' => 'John',
                ],
            ])
            ->add('lastName', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Last name is required']),
                ],
                'attr' => [
                    'placeholder' => 'Doe',
                ],
            ])
            ->add('phone', TelType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => '+1 234 567 8900',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CustomerInfoDTO::class,
        ]);
    }
}

