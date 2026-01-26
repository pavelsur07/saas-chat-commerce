<?php

namespace App\DataFixtures\Crm;

use App\DataFixtures\ClientFixtures;
use App\DataFixtures\CompanyFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Company\Company;
use App\Account\Entity\User;
use App\Entity\Crm\CrmDeal;
use App\Entity\Crm\CrmPipeline;
use App\Entity\Crm\CrmStage;
use App\Entity\Messaging\Client as MessagingClient;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use OutOfBoundsException;
use Ramsey\Uuid\Uuid;

class CrmFixtures extends Fixture implements DependentFixtureInterface
{
    public const REFERENCE_PIPELINE_TELEGRAM = 'crm_pipeline_telegram_sales';
    public const REFERENCE_STAGE_NEW = 'crm_stage_new';
    public const REFERENCE_STAGE_IN_PROGRESS = 'crm_stage_in_progress';
    public const REFERENCE_STAGE_CONTRACT = 'crm_stage_contract';
    public const REFERENCE_STAGE_WON = 'crm_stage_won';
    public const REFERENCE_STAGE_LOST = 'crm_stage_lost';

    public function load(ObjectManager $manager): void
    {
        /** @var Company $company */
        $company = $this->getReference(CompanyFixtures::REFERENCE_COMPANY_1, Company::class);

        $pipeline = new CrmPipeline(Uuid::uuid4()->toString(), $company);
        $pipeline->setName('Продажи Telegram');
        $pipeline->setSlug('telegram-sales');
        $pipeline->setIsDefault(true);
        $manager->persist($pipeline);
        $this->setReference(self::REFERENCE_PIPELINE_TELEGRAM, $pipeline);

        $stageDefinitions = [
            self::REFERENCE_STAGE_NEW => [
                'name' => 'Новый',
                'position' => 1,
                'color' => '#38BDF8',
                'probability' => 10,
                'isStart' => true,
            ],
            self::REFERENCE_STAGE_IN_PROGRESS => [
                'name' => 'В работе',
                'position' => 2,
                'color' => '#60A5FA',
                'probability' => 30,
            ],
            self::REFERENCE_STAGE_CONTRACT => [
                'name' => 'Договор',
                'position' => 3,
                'color' => '#818CF8',
                'probability' => 60,
            ],
            self::REFERENCE_STAGE_WON => [
                'name' => 'Оплачено (Won)',
                'position' => 4,
                'color' => '#22C55E',
                'probability' => 100,
                'isWon' => true,
            ],
            self::REFERENCE_STAGE_LOST => [
                'name' => 'Отказ (Lost)',
                'position' => 5,
                'color' => '#F87171',
                'probability' => 0,
                'isLost' => true,
            ],
        ];

        $stages = [];
        foreach ($stageDefinitions as $reference => $definition) {
            $stage = new CrmStage(Uuid::uuid4()->toString(), $pipeline);
            $stage->setName($definition['name']);
            $stage->setPosition($definition['position']);
            $stage->setColor($definition['color']);
            $stage->setProbability($definition['probability']);

            if (($definition['isStart'] ?? false) === true) {
                $stage->setIsStart(true);
            }

            if (($definition['isWon'] ?? false) === true) {
                $stage->setIsWon(true);
            }

            if (($definition['isLost'] ?? false) === true) {
                $stage->setIsLost(true);
            }

            $manager->persist($stage);
            $this->setReference($reference, $stage);
            $stages[$reference] = $stage;
        }

        /** @var User $createdBy */
        $createdBy = $this->getReference(UserFixtures::REFERENCE_USER_1_ADMIN, User::class);

        $owner = null;
        try {
            /** @var User $ownerRef */
            $ownerRef = $this->getReference(UserFixtures::REFERENCE_USER_2_OPERATOR, User::class);
            $owner = $ownerRef;
        } catch (OutOfBoundsException) {
            $owner = null;
        }

        $clientPool = $manager
            ->getRepository(MessagingClient::class)
            ->findBy(['company' => $company]);

        $dealDefinitions = [
            [
                'stage' => self::REFERENCE_STAGE_NEW,
                'title' => 'Новая заявка из Telegram: леггинсы Classic',
                'amount' => '3500.00',
                'openedDaysAgo' => 1,
                'note' => 'Клиент интересуется доставкой по Москве.',
            ],
            [
                'stage' => self::REFERENCE_STAGE_NEW,
                'title' => 'Запрос по худи Oversize',
                'amount' => '5200.00',
                'openedDaysAgo' => 2,
                'note' => 'Нужен размер L и консультация по цвету.',
            ],
            [
                'stage' => self::REFERENCE_STAGE_IN_PROGRESS,
                'title' => 'Подбор комплекта для студии',
                'amount' => '9800.00',
                'openedDaysAgo' => 4,
                'note' => 'Обсудили таблицу размеров, ждёт фото.',
            ],
            [
                'stage' => self::REFERENCE_STAGE_IN_PROGRESS,
                'title' => 'Повторный заказ от постоянного клиента',
                'amount' => '6400.00',
                'openedDaysAgo' => 3,
                'note' => 'Просит закрепить персонального менеджера.',
            ],
            [
                'stage' => self::REFERENCE_STAGE_CONTRACT,
                'title' => 'Юрлицо: счёт на 15 комплектов',
                'amount' => '28500.00',
                'openedDaysAgo' => 5,
                'note' => 'Подготовлен договор, ожидаем подпись.',
            ],
            [
                'stage' => self::REFERENCE_STAGE_CONTRACT,
                'title' => 'Корзина с сайта: нужно КП',
                'amount' => '8700.00',
                'openedDaysAgo' => 6,
                'note' => 'Отправили спецификацию и условия оплаты.',
            ],
            [
                'stage' => self::REFERENCE_STAGE_WON,
                'title' => 'Оплата через Telegram Pay',
                'amount' => '4500.00',
                'openedDaysAgo' => 8,
                'closedDaysAgo' => 2,
                'note' => 'Клиент доволен, оставит отзыв.',
            ],
            [
                'stage' => self::REFERENCE_STAGE_WON,
                'title' => 'Подписка на ежемесячные поставки',
                'amount' => '12900.00',
                'openedDaysAgo' => 10,
                'closedDaysAgo' => 1,
                'note' => 'Оформили автооплату на следующий месяц.',
            ],
            [
                'stage' => self::REFERENCE_STAGE_LOST,
                'title' => 'Отказ: не подошёл размер',
                'amount' => '3200.00',
                'openedDaysAgo' => 7,
                'closedDaysAgo' => 3,
                'lossReason' => 'Не подошёл размер, ищет другой бренд.',
                'note' => 'Предложили альтернативу, но клиент отказался.',
            ],
            [
                'stage' => self::REFERENCE_STAGE_LOST,
                'title' => 'Отказ: долго ждать предзаказ',
                'amount' => '7600.00',
                'openedDaysAgo' => 12,
                'closedDaysAgo' => 6,
                'lossReason' => 'Критичны сроки поставки.',
                'note' => 'Попросили уведомить при появлении на складе.',
            ],
        ];

        foreach ($dealDefinitions as $index => $definition) {
            $openedAt = new \DateTimeImmutable(sprintf('-%d days', $definition['openedDaysAgo']));
            $deal = new CrmDeal(
                Uuid::uuid4()->toString(),
                $company,
                $pipeline,
                $stages[$definition['stage']],
                $createdBy,
                $definition['title'],
                $openedAt,
            );
            $deal->setAmount($definition['amount']);
            $deal->setSource('telegram');
            $deal->setNote($definition['note'] ?? null);

            if (isset($definition['closedDaysAgo'])) {
                $deal->setIsClosed(true);
                $deal->setClosedAt(new \DateTimeImmutable(sprintf('-%d days', $definition['closedDaysAgo'])));
            }

            if (isset($definition['lossReason'])) {
                $deal->setLossReason($definition['lossReason']);
            }

            if ($owner instanceof User) {
                $deal->setOwner($owner);
            }

            if (!empty($clientPool)) {
                $deal->setClient($clientPool[$index % count($clientPool)]);
            }

            $manager->persist($deal);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CompanyFixtures::class,
            UserFixtures::class,
            ClientFixtures::class,
        ];
    }
}
