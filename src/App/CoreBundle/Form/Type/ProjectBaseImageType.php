<?php

namespace App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ProjectBaseImageType extends AbstractType
{
    /**
     * @var
     */
    protected $class;

    /**
     * @param $class
     */
    public function __construct($class)
    {
        $this->class = $class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('docker_base_image', 'choice', [
                'label' => false,
                'choices' => [
                    'stage1' => 'Stage1 Base',
                    'symfony2' => 'Symfony2',
                ]
            ])
            ->add('save', 'submit', ['label' => 'Save project\'s base image']);
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'data_class' => $this->class
        ]);
    }

    public function getName()
    {
        return 'project_base_image';
    }
}
