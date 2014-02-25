<?php

namespace App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ProjectSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('buildYml', 'ace_editor', [
                'mode' => 'ace/mode/yaml',
                'width' => '50%',
                'tab_size' => 2,
            ])
            ->add('save', 'submit', [
                'label' => 'Save settings',
                'attr' => ['class' => 'btn btn-primary']
            ])
            ->add('save_and_build', 'submit', [
                'label' => 'Save and build with these settings',
                'attr' => ['class' => 'btn']
            ]);
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'App\\CoreBundle\\Entity\\ProjectSettings',
        ]);
    }

    public function getName()
    {
        return 'project_settings';
    }
}