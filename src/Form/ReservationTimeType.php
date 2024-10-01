<?php

namespace App\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationTimeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder
            ->add('createdAt', ChoiceType::class, [
                'choices' => array_combine($options['available_slots'], $options['available_slots']),
                'label' => 'Créneaux disponibles',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'available_slots' => [], // Définir une valeur par défaut pour available_slots
            'date' => null, // Définir une valeur par défaut pour la date
        ]);
    }
}
