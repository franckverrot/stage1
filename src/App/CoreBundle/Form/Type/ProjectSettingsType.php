<?php

namespace App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ProjectSettingsType extends AbstractType
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
            ->add('buildYml', 'ace_editor', [
                'mode'              => 'ace/mode/yaml',
                'theme'             => 'ace/theme/dawn',
                'width'             => 'undefined',
                'tab_size'          => 2,
                'show_print_margin' => false,
                'font_size'         => 13,
                'height'            => 400,
                'label'             => 'Build configuration'
            ])
            ->add('save', 'submit', [
                'label' => 'Save settings',
                'attr'  => ['class' => 'btn btn-primary']
            ])
            ->add('save_and_build', 'submit', [
                'label' => 'Save and build with these settings',
                'attr'  => ['class' => 'btn']
            ]);
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'data_class' => $this->class,
            'intention'  => 'yml_settings'
        ]);
    }

    public function getName()
    {
        return 'project_settings';
    }
}
