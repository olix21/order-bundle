<?php

namespace Dywee\OrderBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OfferElementRentType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('beginAt', DateType::class, array(
                'input' => 'datetime',
                'widget' => 'single_text'
            ))
            ->add('endAt', DateType::class, array(
                'input' => 'datetime',
                'widget' => 'single_text'
            ));
        ;
    }
    
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Dywee\OrderBundle\Entity\OfferElement'
        ));
    }


    public function getParent()
    {
        return OfferElementType::class;
    }
}
