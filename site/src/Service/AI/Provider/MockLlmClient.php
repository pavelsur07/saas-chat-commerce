<?php

declare(strict_types=1);

namespace App\Service\AI\Provider;

use App\Service\AI\LlmClient;

final class MockLlmClient implements LlmClient
{
    public function chat(array $opts): array
    {
        // Новый контракт: messages[]
        $messages = is_array($opts['messages'] ?? null) ? $opts['messages'] : [];

        $system = '';
        $lastUser = '';
        foreach ($messages as $m) {
            $role = (string) ($m['role'] ?? '');
            $content = (string) ($m['content'] ?? '');
            if ('system' === $role) {
                // возьмём первый system блок
                if ('' === $system) {
                    $system = $content;
                }
            }
            if ('user' === $role) {
                $lastUser = $content; // последняя user-реплика
            }
        }
        $system = (string) $system;
        $user = trim((string) $lastUser);

        // извлечём подсказки из system (Tone/Brand/Knowledge)
        $hints = [
            'hasDelivery' => (bool) preg_match('/Knowledge Snippets:.*достав/ius', $system),
            'hasReturn' => (bool) preg_match('/Knowledge Snippets:.*возврат/i', $system),
            'hasTall' => (bool) preg_match('/Knowledge Snippets:.*(tall|высок)/i', $system),
            'hasFabric' => (bool) preg_match('/Knowledge Snippets:.*(ткан|материал)/i', $system),
            'hasSexy' => (bool) preg_match('/Knowledge Snippets:.*sexy/i', $system),
        ];

        $u = mb_strtolower($user);
        $s = [];

        // Доставка
        if (str_contains($u, 'достав') || str_contains($u, 'ship')) {
            $s[] = $hints['hasDelivery']
                ? 'Доставка по РФ/СНГ 2–7 дней; по Москве 1–3. Подскажите город — уточню.'
                : 'Подскажите город — скажу точные сроки и стоимость доставки.';
        }
        // Возврат
        if (str_contains($u, 'возврат') || str_contains($u, 'return')) {
            $s[] = $hints['hasReturn']
                ? 'Возврат 14 дней: бирки не снимать, вещь без следов носки. Подсказать шаги?'
                : 'Помогу с возвратом: уточните причину — подскажу шаги.';
        }
        // Размеры/Tall
        if (str_contains($u, 'размер') || str_contains($u, 'рост') || str_contains($u, 'об ')) {
            $s[] = $hints['hasTall']
                ? 'Скиньте ОГ/ОТ/ОБ и рост — подберу. Для высоких есть линейка Tall (+6 см).'
                : 'Скиньте ОГ/ОТ/ОБ и рост — подберу размер быстро.';
        }
        if (str_contains($u, 'высок') || str_contains($u, 'tall')) {
            $s[] = $hints['hasTall']
                ? 'Есть модели для высоких: прибавка +6 см к длине шага.'
                : 'Уточните рост — подскажу, какие модели сядут по длине.';
        }
        // Материал/присед
        if (str_contains($u, 'просвеч') || str_contains($u, 'squat')) {
            $s[] = $hints['hasFabric']
                ? 'Плотная ткань, не просвечивает в приседе. Хотите фото/видео?'
                : 'Ткань плотная и эластичная — сидит уверенно.';
        }
        // Sexy/велосипедки
        if (str_contains($u, 'sexy') || str_contains($u, 'велосипед')) {
            $s[] = $hints['hasSexy']
                ? 'Sexy — high-waist, лёгкий push-up; есть велосипедки из той же ткани.'
                : 'Есть high-waist модели и велосипедки — подскажу, что лучше.';
        }

        if (count($s) < 3) {
            $s[] = 'Подскажите город и модель/размер — отвечу точнее и предложу вариант.';
        }

        // подрежем и оставим до 4 вариантов
        $s = array_slice(array_values(array_unique(array_filter(array_map(
            fn ($x) => mb_strlen($x) > 140 ? mb_substr($x, 0, 137).'...' : $x, $s
        )))), 0, 4);

        // НОВЫЙ контракт: content = JSON-строка, как будто это ответ модели
        $json = json_encode(['suggestions' => $s], JSON_UNESCAPED_UNICODE);

        return [
            'content' => $json,
            'usage' => [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
            ],
            'cost_usd' => '0',
            // meta — можно оставить пустым или положить debug
        ];
    }
}
