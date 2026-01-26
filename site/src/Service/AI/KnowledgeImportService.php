<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Entity\AI\CompanyKnowledge;
use App\Entity\AI\Enum\KnowledgeType;
use App\Account\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

final class KnowledgeImportService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Разбор содержимого файла в список записей.
     * Поддержка: Markdown (## Заголовок) и CSV (title,content[,type]).
     *
     * @return array<int,array{title:string, content:string, type?:string}>
     */
    public function parse(string $mimeOrName, string $contents): array
    {
        $m = mb_strtolower(trim($mimeOrName));
        if (str_contains($m, 'text/markdown') || str_ends_with($m, '.md')) {
            return $this->parseMarkdown($contents);
        }
        if (str_contains($m, 'text/csv') || str_ends_with($m, '.csv')) {
            return $this->parseCsv($contents);
        }

        // По умолчанию пытаемся как markdown
        return $this->parseMarkdown($contents);
    }

    /** @return array<int,array{title:string, content:string, type?:string}> */
    private function parseMarkdown(string $md): array
    {
        $lines = preg_split('/\R/u', $md) ?: [];
        $items = [];
        $title = 'Без названия';
        $buf = [];

        foreach ($lines as $line) {
            $t = trim($line);
            if (preg_match('/^##\s+(.+)$/u', $t, $m)) {
                if ($buf) {
                    $items[] = ['title' => $title, 'content' => trim(implode("\n", $buf))];
                    $buf = [];
                }
                $title = trim($m[1]);
            } else {
                $buf[] = $line;
            }
        }
        if ($buf) {
            $items[] = ['title' => $title, 'content' => trim(implode("\n", $buf))];
        }

        // пустые отбросим
        return array_values(array_filter($items, fn ($x) => ($x['content'] ?? '') !== ''));
    }

    /** @return array<int,array{title:string, content:string, type?:string}> */
    private function parseCsv(string $csv): array
    {
        $rows = [];
        $f = fopen('php://memory', 'r+');
        fwrite($f, $csv);
        rewind($f);
        while (($r = fgetcsv($f, 0, ',', '"', '\\')) !== false) {
            $rows[] = $r;
        }
        fclose($f);

        if (!$rows) {
            return [];
        }
        $header = array_map(fn ($x) => mb_strtolower(trim((string) $x)), $rows[0]);

        $ti = array_search('title', $header, true);
        $ci = array_search('content', $header, true);
        $yi = array_search('type', $header, true); // опционально

        if (false === $ti || false === $ci) {
            throw new \InvalidArgumentException('CSV должен иметь колонки: title, content (и опционально type)');
        }

        $items = [];
        for ($i = 1; $i < count($rows); ++$i) {
            $title = (string) ($rows[$i][$ti] ?? '');
            $content = (string) ($rows[$i][$ci] ?? '');
            $type = false !== $yi ? (string) ($rows[$i][$yi] ?? '') : '';
            if ('' === $title && '' === $content) {
                continue;
            }
            $it = ['title' => ('' !== $title ? $title : 'Без названия'), 'content' => $content];
            if ('' !== $type) {
                $it['type'] = $type;
            }
            $items[] = $it;
        }

        return $items;
    }

    /**
     * Публикация в CompanyKnowledge (строго по вашему конструктору и сеттерам).
     * - Новый: new CompanyKnowledge($id, $company, $type, $title, $content); setTags('published');
     * - Существующий (по title внутри компании): обновляем type/content/tags.
     *
     * @return int кол-во затронутых записей
     */
    public function publish(Company $company, array $items, bool $replaceSameTitles = true): int
    {
        $repo = $this->em->getRepository(CompanyKnowledge::class);
        $n = 0;

        foreach ($items as $it) {
            $title = trim((string) ($it['title'] ?? ''));
            $content = (string) ($it['content'] ?? '');
            if ('' === $content) {
                continue;
            }

            // type: из CSV/MD — опционально, по умолчанию FAQ
            $rawType = mb_strtolower((string) ($it['type'] ?? 'faq'));
            $type = match ($rawType) {
                'delivery' => KnowledgeType::DELIVERY,
                'product' => KnowledgeType::PRODUCT,
                'policy' => KnowledgeType::POLICY,
                default => KnowledgeType::FAQ,
            };

            /** @var CompanyKnowledge|null $ck */
            $ck = null;
            if ($replaceSameTitles) {
                $ck = $repo->findOneBy(['company' => $company, 'title' => $title]);
            }

            if (null === $ck) {
                // ваш КОНСТРУКТОР: (string $id, Company $company, KnowledgeType $type, string $title, string $content)
                $ck = new CompanyKnowledge(
                    Uuid::uuid4()->toString(),
                    $company,
                    $type,
                    '' !== $title ? $title : 'Без названия',
                    $content
                );
                // родные сеттеры (существуют в вашей модели): setTags(?string)
                $ck->setTags('published');
                $this->em->persist($ck);
                ++$n;
            } else {
                // родные сеттеры: setType, setContent, setTags
                $ck->setType($type);
                $ck->setContent($content);
                $ck->setTags('published');
                ++$n;
            }
        }

        $this->em->flush();

        return $n;
    }
}
