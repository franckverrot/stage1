<?php

namespace App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ProjectMasterPasswordType extends AbstractType
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
            ->add('master_password', 'password', ['required' => false])
            ->add('update', 'submit')
            ->add('delete', 'submit');
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'data_class' => $this->class
        ]);
    }

    public function getName()
    {
        return 'project_master_password';
    }
}
