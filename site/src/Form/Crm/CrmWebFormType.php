<?php

namespace App\Form\Crm;

use App\Entity\Company\Company;
use App\Entity\Company\User;
use App\Entity\Company\UserCompany;
use App\Entity\Crm\CrmPipeline;
use App\Entity\Crm\CrmStage;
use App\Entity\Crm\CrmWebForm;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
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
            ])
            ->add('successRedirectUrl', TextType::class, [
                'label' => 'URL для редиректа',
                'required' => false,
            ])
            ->add('fields', TextareaType::class, [
                'label' => 'Поля формы (JSON)',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'spellcheck' => 'false',
                    'class' => 'font-mono text-sm',
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
