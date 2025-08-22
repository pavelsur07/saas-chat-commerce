<?php

// src/Form/AI/AiCompanyProfileType.php

namespace App\Form\AI;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class AiCompanyProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $o): void
    {
        $b->add('toneOfVoice', TextareaType::class, [
            'label' => 'Tone of Voice',
            'required' => false,
            'attr' => ['rows' => 6, 'placeholder' => 'Дружелюбный, краткий, уверенный…'],
        ])
            ->add('brandNotes', TextareaType::class, [
                'label' => 'Брендовые заметки',
                'required' => false,
                'attr' => ['rows' => 6, 'placeholder' => 'Позиционирование, УТП, принципы общения…'],
            ])
            ->add('language', ChoiceType::class, [
                'label' => 'Язык',
                'choices' => [
                    'Русский (ru-RU)' => 'ru-RU',
                    'English (en-US)' => 'en-US',
                ],
            ]);
    }
}
