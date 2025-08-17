<?php

namespace App\Controller\Admin;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AiLogAdminController extends AbstractController
{
    public function __construct(private readonly Connection $db)
    {
    }

    #[Route('/admin/ai/logs', name: 'admin.ai_logs', methods: ['GET'])]
    public function index(): Response
    {
        // Берём последние 50 записей из таблицы ai_prompt_log
        // (Если у тебя есть ORM-сущность AiPromptLog — можно заменить на репозиторий)
        $rows = $this->db->fetchAllAssociative('
            SELECT id, company_id, kind, model, input_json, output_json, success, latency_ms, created_at
            FROM ai_prompt_log
            ORDER BY created_at DESC
            LIMIT 50
        ');

        // Декодируем JSON-колонки для удобного вывода
        $logs = array_map(function (array $r) {
            $r['input_json'] = self::safeJsonDecode($r['input_json']);
            $r['output_json'] = self::safeJsonDecode($r['output_json']);

            return $r;
        }, $rows);

        return $this->render('admin/ai_logs/index.html.twig', [
            'logs' => $logs,
        ]);
    }

    private static function safeJsonDecode(?string $json): mixed
    {
        if (null === $json || '' === $json) {
            return null;
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return $data;
        } catch (\Throwable) {
            return $json; // если невалидный JSON — покажем сырой текст
        }
    }
}
