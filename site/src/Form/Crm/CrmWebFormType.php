<?php

namespace App\Form\Crm;

use App\Account\Entity\Company;
use App\Account\Entity\User;
use App\Account\Entity\UserCompany;
use App\Entity\Crm\CrmPipeline;
use App\Entity\Crm\CrmStage;
use App\Entity\Crm\CrmWebForm;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CrmWebFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $company = $options['company'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'Название формы',
                'constraints' => [
                    new NotBlank(message: 'Укажите название формы'),
                ],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'help' => 'Должен быть уникален в пределах компании',
                'constraints' => [
                    new NotBlank(message: 'Укажите slug'),
                ],
            ])
            ->add('pipeline', EntityType::class, [
                'label' => 'Воронка',
                'class' => CrmPipeline::class,
                'choice_label' => 'name',
                'placeholder' => 'Выберите воронку',
                'query_builder' => static fn (EntityRepository $repo) => $repo
                    ->createQueryBuilder('pipeline')
                    ->andWhere('pipeline.company = :company')
                    ->setParameter('company', $company)
                    ->orderBy('pipeline.name', 'ASC'),
            ])
            ->add('stage', EntityType::class, [
                'label' => 'Этап',
                'class' => CrmStage::class,
                'choice_label' => static fn (CrmStage $stage): string => sprintf('%s — %s', $stage->getPipeline()->getName(), $stage->getName()),
                'placeholder' => 'Выберите этап',
                'query_builder' => static fn (EntityRepository $repo) => $repo
                    ->createQueryBuilder('stage')
                    ->join('stage.pipeline', 'pipeline')
                    ->andWhere('pipeline.company = :company')
                    ->setParameter('company', $company)
                    ->orderBy('pipeline.name', 'ASC')
                    ->addOrderBy('stage.position', 'ASC'),
            ])
            ->add('owner', EntityType::class, [
                'label' => 'Ответственный',
                'class' => User::class,
                'required' => false,
                'choice_label' => 'email',
                'placeholder' => 'Не выбран',
                'query_builder' => static fn (EntityRepository $repo) => $repo
                    ->createQueryBuilder('user')
                    ->innerJoin(UserCompany::class, 'uc', 'WITH', 'uc.user = user')
                    ->andWhere('uc.company = :company')
                    ->andWhere('uc.status = :status')
                    ->setParameter('company', $company)
                    ->setParameter('status', UserCompany::STATUS_ACTIVE)
                    ->orderBy('user.email', 'ASC'),
            ])
            ->add('successType', ChoiceType::class, [
                'label' => 'Действие после отправки',
                'choices' => [
                    'Показать сообщение' => 'message',
                    'Редирект' => 'redirect',
                ],
            ])
            ->add('successMessage', TextareaType::class, [
                'label' => 'Сообщение об успехе',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                ],
                'help' => 'Можно добавить ссылку вида <a href="https://example.com/next">перейти далее</a> — она будет показана в сообщении.',
            ])
            ->add('successRedirectUrl', TextType::class, [
                'label' => 'URL для редиректа',
                'required' => false,
            ])
            ->add('fields', TextareaType::class, [
                'label' => 'Поля формы (JSON)',
                'required' => false,
                'attr' => [
                    'rows' => 1,
                    'spellcheck' => 'false',
                    'class' => 'font-mono text-sm text-xs',
                    'data-webform-fields-target' => 'textarea',
                ],
                'help' => 'На первом этапе можно оставить пустым или указать JSON-массив полей',
            ])
            ->add('tags', TextareaType::class, [
                'label' => 'Теги (по одному в строке)',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                ],
            ])
            ->add('allowedOrigins', TextareaType::class, [
                'label' => 'Разрешённые Origin (по одному в строке или JSON массив строк)',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'spellcheck' => 'false',
                    'class' => 'font-mono text-sm',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Форма активна',
                'required' => false,
            ])
        ;

        $builder->get('tags')->addModelTransformer(new CallbackTransformer(
            static fn (?array $tags): string => implode("\n", $tags ?? []),
            static function (?string $value): array {
                $value = trim((string) ($value ?? ''));
                if ($value === '') {
                    return [];
                }

                $lines = preg_split('/[\r\n]+/', $value) ?: [];
                $lines = array_values(array_filter(array_map(static fn (string $line): string => trim($line), $lines), static fn (string $line): bool => $line !== ''));

                return $lines;
            }
        ));

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

                    throw new TransformationFailedException('Не удалось распознать список origin. Используйте JSON массив или укажите каждый origin с новой строки.', 0, $exception);
                }
            }
        ));

        $builder->get('fields')->addModelTransformer(new CallbackTransformer(
            static fn (?array $fields): string => $fields === [] ? '' : json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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

                    return array_values($decoded);
                } catch (\JsonException $exception) {
                    throw new TransformationFailedException('Не удалось разобрать JSON полей: ' . $exception->getMessage(), 0, $exception);
                }
            }
        ));

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $data = $event->getData();

            if (!\is_array($data)) {
                return;
            }

            if (!\array_key_exists('fields', $data)) {
                return;
            }

            $raw = $data['fields'];

            if ($raw === null || $raw === '') {
                try {
                    $data['fields'] = json_encode([], JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    $form->get('fields')->addError(new FormError('Не удалось сохранить пустой список полей.'));

                    return;
                }

                $event->setData($data);

                return;
            }

            if (!\is_string($raw)) {
                $form->get('fields')->addError(new FormError('Неверный формат JSON.'));

                return;
            }

            $decoded = null;
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $form->get('fields')->addError(new FormError('Не удалось разобрать JSON. Проверьте синтаксис.'));

                return;
            }

            if (!\is_array($decoded)) {
                $form->get('fields')->addError(new FormError('JSON должен быть массивом полей.'));

                return;
            }

            $normalized = [];
            $allowedTypes = ['text', 'textarea', 'email', 'tel', 'checkbox', 'select'];

            foreach ($decoded as $index => $item) {
                if (!\is_array($item)) {
                    $form->get('fields')->addError(new FormError(sprintf('Элемент #%d имеет неверный формат.', $index + 1)));

                    return;
                }

                $key = isset($item['key']) ? (string) $item['key'] : '';
                $label = isset($item['label']) ? (string) $item['label'] : '';
                $type = isset($item['type']) ? (string) $item['type'] : 'text';
                $required = isset($item['required']) ? (bool) $item['required'] : false;
                $placeholder = isset($item['placeholder']) ? (string) $item['placeholder'] : '';
                $options = isset($item['options']) && \is_array($item['options']) ? $item['options'] : [];

                if ($key === '') {
                    $form->get('fields')->addError(new FormError(sprintf('Элемент #%d: не заполнен "key".', $index + 1)));

                    return;
                }

                if ($label === '') {
                    $form->get('fields')->addError(new FormError(sprintf('Элемент #%d: не заполнен "label".', $index + 1)));

                    return;
                }

                if (!\in_array($type, $allowedTypes, true)) {
                    $form->get('fields')->addError(new FormError(sprintf('Элемент #%d: недопустимый тип "%s".', $index + 1, $type)));

                    return;
                }

                $normalized[] = [
                    'key' => $key,
                    'label' => $label,
                    'type' => $type,
                    'required' => $required,
                    'placeholder' => $placeholder,
                    'options' => $options,
                ];
            }

            try {
                $data['fields'] = json_encode($normalized, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $form->get('fields')->addError(new FormError('Не удалось сохранить поля в JSON.'));

                return;
            }

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CrmWebForm::class,
        ]);

        $resolver->setRequired('company');
        $resolver->setAllowedTypes('company', Company::class);
    }
}
