<?php

// src/Form/AI/CompanyKnowledgeType.php

namespace App\Form\AI;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class CompanyKnowledgeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $o): void
    {
        $b->add('type', ChoiceType::class, [
            'label' => 'Тип',
            'choices' => [
                'FAQ' => 'faq',
                'Доставка' => 'delivery',
                'Продукты' => 'product',
                'Политики' => 'policy',
            ],
        ])
            ->add('question', TextType::class, [
                'label' => 'Заголовок/Вопрос',
            ])
            ->add('answer', TextareaType::class, [
                'label' => 'Ответ/Содержание',
                'required' => false,
                'attr' => ['rows' => 8],
            ]);
    }
}
