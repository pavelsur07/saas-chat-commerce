<?php

declare(strict_types=1);

namespace App\Service\Messaging\Middleware;

use App\Account\Entity\Company;
use App\Entity\Messaging\Client as DbClient;
use App\Entity\Messaging\Message as DbMessage;
use App\Repository\Messaging\MessageRepository;
use App\Service\AI\AiFeature;
use App\Service\AI\LlmClient;
use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\Pipeline\MessageMiddlewareInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class AiEnrichMiddleware implements MessageMiddlewareInterface
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly MessageRepository $messages,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(InboundMessage $m, callable $next): void
    {
        if ('' === trim((string) $m->text)) {
            $next($m);

            return;
        }

        try {
            $this->enrichWithAi($m);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to enrich inbound message using LLM', [
                'exception' => $e,
                'channel' => $m->channel,
                'client_id' => $m->clientId,
                'message_id' => $m->meta['_persisted_message_id'] ?? null,
            ]);
        }

        $next($m);
    }

    private function enrichWithAi(InboundMessage $m): void
    {
        $company = $m->meta['company'] ?? null;
        if (!$company instanceof Company && ($m->meta['_client'] ?? null) instanceof DbClient) {
            $company = $m->meta['_client']->getCompany();
        }

        if (!$company instanceof Company) {
            return;
        }

        $intentRes = $this->llm->chat([
            'model' => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $m->text]],
            'feature' => AiFeature::INTENT_CLASSIFY->value,
            'channel' => $m->channel,
            'company' => $company,
            'metadata' => ['message_direction' => 'in'],
        ]);

        if (!empty($m->meta['_persisted_message_id'])) {
            /** @var DbMessage|null $dbMsg */
            $dbMsg = $this->messages->find($m->meta['_persisted_message_id']);
            if ($dbMsg) {
                $meta = $dbMsg->getMeta() ?? [];
                if (!is_array($meta)) {
                    $meta = [];
                }
                $meta['intent'] = trim((string) ($intentRes['content'] ?? ''));
                $dbMsg->setMeta($meta);
                $this->em->flush();
            }
        }

        // Подсказки оператору (по желанию вынести в очередь)
        $this->llm->chat([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Коротко, 2 варианта, дружелюбно.'],
                ['role' => 'user', 'content' => sprintf("Клиент: %s\nДай 2 варианта ответа.", $m->text)],
            ],
            'feature' => AiFeature::AGENT_SUGGEST_REPLY->value,
            'channel' => $m->channel,
            'company' => $company,
            'metadata' => ['message_direction' => 'in'],
        ]);
    }
}
