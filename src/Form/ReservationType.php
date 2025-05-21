<?php

// src/Form/ReservationType.php

namespace App\Form;

use App\Entity\Reservation;
use App\Entity\Room;
use App\Entity\Hotel;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('clientName', TextType::class)
            ->add('clientEmail', EmailType::class)
            ->add('startDate', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Check-in Date',
            ])
            ->add('endDate', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Check-out Date',
            ])
            ->add('room', EntityType::class, [
                'class' => Room::class,
                'choice_label' => 'number',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}
