<?php

namespace App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ProjectAccessType extends AbstractType
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
        $builder->add('ip', 'text');
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'data_class' => $this->class,
            'intention' => 'access_ip',
        ]);
    }

    public function getName()
    {
        return 'project_access';
    }
}
