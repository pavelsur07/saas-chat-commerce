<?php

declare(strict_types=1);

namespace App\Service\AI\Provider;

use App\Service\AI\LlmClient;

final class MockLlmClient implements LlmClient
{
    public function chat(array $opts): array
    {
        $system = (string) ($opts['system'] ?? '');
        $user = trim((string) $opts['user']);

        // выдёргиваем простые факты из system (Tone/Brand/Knowledge)
        $hints = [
            'hasDelivery' => (bool) preg_match('/Knowledge Snippets:.*достав/ius', $system),
            'hasReturn' => (bool) preg_match('/Knowledge Snippets:.*возврат/i', $system),
            'hasTall' => (bool) preg_match('/Knowledge Snippets:.*(tall|высок)/i', $system),
            'hasFabric' => (bool) preg_match('/Knowledge Snippets:.*(ткан|материал)/i', $system),
            'hasSexy' => (bool) preg_match('/Knowledge Snippets:.*sexy/i', $system),
        ];

        $u = mb_strtolower($user);
        $s = [];

        if (str_contains($u, 'достав') or str_contains($u, 'ship')) {
            $s[] = $hints['hasDelivery']
                ? 'Доставка по РФ/СНГ 2–7 дней; по Москве 1–3. Подскажите город — уточню.'
                : 'Подскажите город — скажу точные сроки и стоимость доставки.';
        }
        if (str_contains($u, 'возврат') or str_contains($u, 'return')) {
            $s[] = $hints['hasReturn']
                ? 'Возврат 14 дней: бирки не снимать, вещь без следов носки. Подсказать шаги?'
                : 'Помогу с возвратом: уточните причину — подскажу шаги.';
        }
        if (str_contains($u, 'размер') or str_contains($u, 'рост') or str_contains($u, 'об ')) {
            $s[] = $hints['hasTall']
                ? 'Скиньте ОГ/ОТ/ОБ и рост — подберу. Для высоких есть линейка Tall (+6 см).'
                : 'Скиньте ОГ/ОТ/ОБ и рост — подберу размер быстро.';
        }
        if (str_contains($u, 'просвеч') or str_contains($u, 'squat')) {
            $s[] = $hints['hasFabric']
                ? 'Плотная ткань, не просвечивает в приседе. Хотите фото/видео?'
                : 'Ткань плотная и эластичная — сидит уверенно.';
        }
        if (str_contains($u, 'sexy') or str_contains($u, 'велосипед')) {
            $s[] = $hints['hasSexy']
                ? 'Sexy — high‑waist, лёгкий push‑up; есть велосипедки из той же ткани.'
                : 'Есть high‑waist модели и велосипедки — подскажу, что лучше.';
        }

        if (count($s) < 3) {
            $s[] = 'Подскажите город и модель/размер — отвечу точнее и предложу вариант.';
        }

        // подрежем и вернём до 4 штук
        $s = array_map(fn ($x) => mb_strlen($x) > 140 ? mb_substr($x, 0, 137).'...' : $x, $s);

        return ['suggestions' => array_slice(array_values(array_unique(array_filter($s))), 0, 4)];
    }
}
