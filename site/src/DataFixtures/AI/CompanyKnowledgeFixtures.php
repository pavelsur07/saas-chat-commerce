<?php

namespace App\DataFixtures\AI;

use App\DataFixtures\CompanyFixtures;
use App\Entity\AI\CompanyKnowledge;
use App\Entity\AI\Enum\KnowledgeType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

class CompanyKnowledgeFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            CompanyFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $knowledge = [
            [
                'title' => 'Доставка',
                'content' => 'Доставка по РФ и СНГ 2–7 дней. По Москве — 1–3 дня. Бесплатно от 7 000 ₽. Для СПб обычно 2–4 дня.',
            ],
            [
                'title' => 'Возврат',
                'content' => 'Возврат в течение 14 дней: бирки не снимать, вещь без следов носки. Деньги вернём за 1–5 рабочих дней.'],
            [
                'title' => 'Оплата',
                'content' => 'Оплата картой онлайн. Рассрочки нет. Наложенный платёж — только на маркетплейсах.',
            ],
            [
                'title' => 'Размерная сетка',
                'content' => 'Размеры от XS до 2XL. Поможем подобрать по ОГ/ОТ/ОБ и росту.',
            ],
            [
                'title' => 'Для высоких (Tall)',
                'content' => 'Есть линейка для высоких: прибавка +6 см к длине шага и +3 см к пояску.',
            ],
            [
                'title' => 'Материал и непрозрачность',
                'content' => 'Итальянская ткань 260–280 г/м², не просвечивает в приседе.',
            ],
            [
                'title' => 'Уход',
                'content' => 'Стирка при 30°C, без отбеливателя, без сушки в барабане.',
            ],
            [
                'title' => 'Леггинсы Sexy (хит)',
                'content' => 'High-waist, лёгкий push-up, есть велосипедки из той же ткани.',
            ],
            [
                'title' => 'Большие размеры',
                'content' => 'Подойдут до 2XL, ткань держит форму, мягко утягивает.',
            ],
            [
                'title' => 'Наличие и предзаказ',
                'content' => 'Пополнение раз в 2–3 недели. Предзаказ от 5 дней.',
            ],
            [
                'title' => 'Акции и Telegram',
                'content' => 'Ранний доступ к цветам и промокоды в Telegram (5–10%).',
            ],
            [
                'title' => 'Консультация по размеру',
                'content' => 'Подберём размер по ОГ/ОТ/ОБ и росту — пишите параметры.',
            ],
        ];

        $company = $this->getReference(CompanyFixtures::REFERENCE_COMPANY_1, \App\Entity\Company\Company::class);

        foreach ($knowledge as $item) {
            $message = new CompanyKnowledge(
                id: Uuid::uuid4()->toString(),
                company: $company,
                type: KnowledgeType::FAQ,
                title: $item['title'],
                content: $item['content'],
            );
            $manager->persist($message);
        }

        $manager->flush();
    }
}
