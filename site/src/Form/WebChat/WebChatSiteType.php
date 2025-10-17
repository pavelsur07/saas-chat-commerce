<?php

namespace App\Form\WebChat;

use App\Entity\WebChat\WebChatSite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class WebChatSiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Название',
                'constraints' => [
                    new NotBlank(message: 'Укажите название сайта'),
                ],
            ])
            ->add('allowedOrigins', TextareaType::class, [
                'label' => 'Разрешённые Origin (по одному в строке или JSON массив строк)',
                'required' => false,
                'attr' => [
                    'rows' => 6,
                    'spellcheck' => 'false',
                    'class' => 'font-mono text-sm',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Сайт активен',
                'required' => false,
            ])
        ;

        $builder->get('allowedOrigins')->addModelTransformer(new CallbackTransformer(
            static function (?array $origins): string {
                if (empty($origins)) {
                    return '';
                }

                return json_encode($origins, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            },
            static function (?string $value): array {
                $value = trim((string) ($value ?? ''));
                if ($value === '') {
                    return [];
                }

                try {
                    $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($decoded)) {
                        throw new TransformationFailedException('JSON должен быть массивом.');
                    }

                    return array_values(array_map(static fn ($origin): string => (string) $origin, $decoded));
                } catch (\JsonException $exception) {
                    $lines = preg_split('/[\r\n,]+/', $value) ?: [];
                    $lines = array_values(array_filter(array_map(static fn (string $origin): string => trim($origin), $lines), static fn (string $origin): bool => $origin !== ''));

                    if ($lines !== []) {
                        return array_map(static fn (string $origin): string => $origin, $lines);
                    }

                    throw new TransformationFailedException('Не удалось распознать список origin. Используйте JSON массив или укажите каждый origin с новой строки.');
                }
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WebChatSite::class,
        ]);
    }
}
