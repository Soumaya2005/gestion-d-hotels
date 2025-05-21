<?php
// src/Form/RoomType.php

namespace App\Form;

use App\Entity\Room;
use App\Entity\Hotel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

class RoomType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('number', TextType::class, [
                'label' => 'Numéro de chambre',
                'constraints' => [
                    new NotBlank(['message' => 'Le numéro de chambre est requis'])
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de chambre',
                'choices' => [
                    'Simple' => 'Simple',
                    'Double' => 'Double',
                    'Suite' => 'Suite',
                    'Familiale' => 'Familiale',
                    'Affaires' => 'Affaires'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le type de chambre est requis'])
                ]
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix par nuit (€)',
                'scale' => 2,
                'constraints' => [
                    new NotBlank(['message' => 'Le prix est requis']),
                    new Positive(['message' => 'Le prix doit être un nombre positif']),
                    new Type(['type' => 'float', 'message' => 'Le prix doit être un nombre valide'])
                ]
            ])
            ->add('hotel', EntityType::class, [
                'class' => Hotel::class,
                'label' => 'Hôtel',
                'choice_label' => 'name',
                'placeholder' => 'Sélectionnez un hôtel',
                'constraints' => [
                    new NotBlank(['message' => 'La sélection d\'un hôtel est requise'])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Room::class,
        ]);
    }
}
