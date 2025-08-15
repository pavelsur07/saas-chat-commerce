<?php

declare(strict_types=1);

namespace App\Service\Messaging\Middleware;

use App\Entity\Messaging\Message as DbMessage;
use App\Repository\Messaging\MessageRepository;
use App\Service\AI\AiFeature;
use App\Service\AI\LlmClient;
use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\Pipeline\MessageMiddlewareInterface;
use Doctrine\ORM\EntityManagerInterface;

final class AiEnrichMiddleware implements MessageMiddlewareInterface
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly MessageRepository $messages,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(InboundMessage $m, callable $next): void
    {
        $intentRes = $this->llm->chat([
            'model' => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $m->text]],
            'feature' => AiFeature::INTENT_CLASSIFY->value,
            'channel' => $m->channel,
        ]);

        if (!empty($m->meta['_persisted_message_id'])) {
            /** @var DbMessage|null $dbMsg */
            $dbMsg = $this->messages->find($m->meta['_persisted_message_id']);
            if ($dbMsg) {
                $meta = $dbMsg->getMeta() ?? [];
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
        ]);

        $next($m);
    }
}
