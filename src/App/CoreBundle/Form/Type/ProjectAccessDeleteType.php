<?php

namespace App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ProjectAccessDeleteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('ip', 'collection', [
                'type' => 'text',
                'allow_delete' => true,
                'options' => ['label' => 'revoke', 'data_class' => 'App\\CoreBundle\\Value\\ProjectAccess']
            ]);
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'method' => 'DELETE',
        ]);
    }

    public function getName()
    {
        return 'project_access_delete';
    }
}