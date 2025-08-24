<?php

// src/Form/AI/CompanyKnowledgeType.php

namespace App\Form\AI;

use App\Entity\AI\Enum\KnowledgeType;
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
            'choices' => KnowledgeType::cases(),
            'choice_label' => fn(KnowledgeType $t) => match ($t) {
                KnowledgeType::FAQ => 'FAQ',
                KnowledgeType::DELIVERY => 'Доставка',
                KnowledgeType::PRODUCT => 'Продукты',
                KnowledgeType::POLICY => 'Политики',
            },
            'choice_value' => fn (?KnowledgeType $t) => $t?->value,
        ])
            ->add('title', TextType::class, [
                'label' => 'Заголовок/Вопрос',
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Ответ/Содержание',
                'required' => false,
                'attr' => ['rows' => 8],
            ]);
    }
}
