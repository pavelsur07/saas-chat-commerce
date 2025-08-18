<?php

namespace App\AI;

use App\Repository\Messaging\MessageRepository;

class ConversationContextProvider
{
    public function __construct(private MessageRepository $messages)
    {
    }

    /**
     * @return array<int,array{role:string,text:string,createdAt:\DateTimeInterface}>
     */
    public function getContext(string $clientId, int $limit = 12, int $maxChars = 4000): array
    {
        $items = $this->messages->findLastByClient($clientId, $limit);

        $result = [];
        $chars = 0;

        foreach ($items as $m) {
            $role = 'in' === $m->getDirection() ? 'user' : 'agent';
            $text = trim((string) $m->getText());
            if ('' === $text) {
                continue;
            }
            $len = mb_strlen($text);
            if ($chars + $len > $maxChars) {
                break;
            }
            $chars += $len;

            $result[] = [
                'role' => $role,
                'text' => $text,
                'createdAt' => $m->getCreatedAt(),
            ];
        }

        return $result;
    }
}
