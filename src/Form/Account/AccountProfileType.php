<?php

namespace App\Form\Account;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;

class AccountProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Length(max: 255)],
                'attr' => ['autocomplete' => 'given-name'],
            ])
            ->add('lastName', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Length(max: 255)],
                'attr' => ['autocomplete' => 'family-name'],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new Email(),
                    new Length(max: 180),
                ],
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('phone', TelType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Length(max: 50)],
                'attr' => ['autocomplete' => 'tel'],
            ])
            ->add('company', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Length(max: 255)],
                'attr' => ['autocomplete' => 'organization'],
            ])
            ->add('addressLine1', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Length(max: 255)],
                'attr' => ['autocomplete' => 'address-line1'],
            ])
            ->add('addressLine2', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Length(max: 255)],
                'attr' => ['autocomplete' => 'address-line2'],
            ])
            ->add('postalCode', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Length(max: 20)],
                'attr' => ['autocomplete' => 'postal-code'],
            ])
            ->add('city', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Length(max: 120)],
                'attr' => ['autocomplete' => 'address-level2'],
            ])
            ->add('state', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Length(max: 120)],
                'attr' => ['autocomplete' => 'address-level1'],
            ])
            ->add('countryCode', CountryType::class, [
                'required' => false,
                'placeholder' => '—',
                'attr' => ['autocomplete' => 'country'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_token_id' => 'account_profile_update',
            'trim' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}

