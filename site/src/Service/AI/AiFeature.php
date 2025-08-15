<?php

declare(strict_types=1);

namespace App\Service\AI;

/**
 * Единый список допустимых feature для LLM-вызовов.
 * Используем для аналитики и логов в ai_prompt_log.metadata.feature.
 */
enum AiFeature: string
{
    case INTENT_CLASSIFY = 'intent_classify';       // Классификация намерения клиента
    case AGENT_SUGGEST_REPLY = 'agent_suggest_reply';   // Подсказки оператору
    case NER_EXTRACT = 'ner_extract';           // Извлечение сущностей (телефон, email, заказ)
    case FAQ_SUGGEST = 'faq_suggest';           // Генерация новых FAQ
    case FAQ_PARAPHRASE = 'faq_paraphrase';        // Перефразирование FAQ
    case SCENARIO_AUTOFILL = 'scenario_autofill';     // Автозаполнение текстов сценария
    case SCENARIO_STEP_SELECT = 'scenario_step_select';  // Выбор следующего шага сценария
    case ECHO_TEST = 'echo_test';             // Тестовое обращение (debug)
}
