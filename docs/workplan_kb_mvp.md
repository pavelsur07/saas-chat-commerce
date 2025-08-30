# Пошаговый план работ (по текущему репозиторию)

Репозиторий распакован и проанализирован: `/mnt/data/project/saas-chat-commerce-master`

Ключевые находки:
- Symfony 7.3 (`/site`), Socket.IO сервер (`/socket-server`).
- Уже есть: `AiCompanyProfile`, `CompanyKnowledge`, `SuggestionService`, `AiSuggestionContextService`, `SuggestionController`, UI Chat Center (`assets/chat-center/*`), миграция `Version202508221035_AiProfileAndKnowledge.php`.
- Нет: `knowledge_source`/`knowledge_chunk`, т.е. слоя файловых источников и чанков; нет таблицы `ai_suggestion_feedback`.

Ниже — минимальный и безопасный маршрут к **«подсказкам, обогащённым знаниями из файлов»**.

---

## 0) База (ветка и базовый прогон)
- [ ] Создать ветку: `feat/kb-mvp`.
- [ ] Убедиться, что локально проходят миграции и тесты:  
  `make composer.install && make db.migrate && make test`  
  (или `php bin/console doctrine:migrations:migrate -n && php bin/phpunit`).
- [ ] Зафиксировать исходный baseline CI.

**DoD:** baseline green.

---

## 1) БД: источники и чанки
Файлы: `/site/migrations/Version20250828*_knowledge_*.php`

- [ ] Таблица `knowledge_source` (версионирование, статус, hash, uploaded_by, is_active).
- [ ] Таблица `knowledge_chunk` (section_path, content, tags[], priority, valid_from/to, tsvector + GIN).
- [ ] Функция/триггер для `tsv` (ru|en).
- [ ] Таблица `ai_suggestion_feedback` (company_id, message_id?, suggestion_text, accepted, created_at).

**DoD:** миграции применяются; `doctrine:schema:validate` — ок.

---

## 2) Хранилище и загрузка (админка)
Файлы: `/site/src/Controller/Admin/KnowledgeSourceController.php`, Twig-шаблоны в `/site/templates/admin/ai/knowledge_source/*`

- [ ] Конфиг `KNOWLEDGE_STORAGE_PATH` (по умолчанию `var/storage/knowledge`).
- [ ] Форма загрузки одного файла (`pdf|docx|txt|md|html`) — в записи `knowledge_source` статус `new`.
- [ ] Список источников, просмотр метаданных, переключатель `is_active`.

**DoD:** можно загрузить файл; запись создаётся; файл лежит в сторадже.

---

## 3) Ingest: парсер + чанкер + CLI
Файлы: `/site/src/Service/AI/Knowledge/SourceParser.php`, `Chunker.php`, `ParsedDocument.php`, `CLI: app:knowledge:ingest`

- [ ] `SourceParser`: поддержка `txt|md|pdf` (docx — опционально). Возвращает чистый текст + секции.
- [ ] `Chunker`: 500–800 символов, overlap 20–30%, секции в `section_path`, авто‑теги (простые словари).
- [ ] Команда:  
  `php bin/console app:knowledge:ingest <file|url> --company=<uuid> --title="..." --type=pdf`  
  создает чанки и помечает `source.status=parsed`.

**DoD:** один файл → N чанков в БД; source=parsed; индексы заполнены.

---

## 4) Поиск чанков
Файлы: `/site/src/Service/AI/Knowledge/ChunkSearchService.php`

- [ ] Метод `search(companyId, query, limit=5)`: `plainto_tsquery` + фильтры `company_id`, `is_active`, даты.
- [ ] Формула: `rank = ts_rank_cd + priority*0.1 + CASE section_path ILIKE :qLike THEN 0.05`.
- [ ] Fallback на `ILIKE`, если короткий запрос.

**DoD:** unit-тесты на релевантность («доставка/оплата»).

---

## 5) Интеграция в подсказки (безопасно)
Файлы: `/site/src/AI/AiSuggestionContextService.php`, `/site/src/AI/SuggestionPromptBuilder.php`, `/site/src/AI/SuggestionService.php`

- [ ] В `AiSuggestionContextService` добавить вызов `ChunkSearchService` (top‑5 по последней фразе клиента).
- [ ] Собирать блок контекста: TOV + (чанки→сниппеты) + (fallback `CompanyKnowledge`).
- [ ] `SuggestionPromptBuilder`: аккуратно форматировать блок (заголовок секции, краткая цитата).
- [ ] Жёсткий JSON-формат оставить прежним.

**DoD:** `/api/suggestions/{clientId}` возвращает релевантные подсказки из свежего документа.

---

## 6) UI Chat Center: feedback и UX
Файлы: `/site/assets/chat-center/components/ChatHints.tsx`, `SendMessageForm.tsx_`, новый эндпойнт feedback

- [ ] Кнопки «👍/👎» к каждой подсказке → `POST /api/suggestions/{clientId}/feedback` (создаём контроллер).
- [ ] Показать спиннер/ошибки; троттлинг вызова (уже есть `SuggestionRateLimiter` на бэке).
- [ ] (Опционально) «Показать источник»: всплывашка со `section_path` для выбранной подсказки.

**DoD:** фидбек пишется; UI не «дребезжит», всё мгновенно.

---

## 7) Админ‑UI: предпросмотр чанков
Файлы: `/site/src/Controller/Admin/KnowledgeSourceController.php` (show), Twig шаблоны

- [ ] Поиск по строке внутри чанков (`?q=`) — быстрый предпросмотр для валидации.
- [ ] Возможность менять `priority` чанка (селект 0..5).

**DoD:** админ подтверждает, что парсинг корректен.

---

## 8) Тесты
- [ ] Unit: `SourceParser`, `Chunker`, `ChunkSearchService`, `AiSuggestionContextService` (сниппеты).
- [ ] Integration: ingest E2E + `/api/suggestions/{clientId}` (факты присутствуют), смена TOV меняет стиль.
- [ ] Smoke prod: загрузить «Оплата/Доставка», спросить в чате → проверить подсказки.

**DoD:** зелёные тесты + смоук‑сценарий в README.

---

## 9) Наблюдаемость и настройки
- [ ] Логи `ai.log` (ingest/search/suggest), Sentry алерты на 5xx.
- [ ] Redis‑кэш популярных чанков (`ai:kb:popular:<cid>` TTL 24ч).
- [ ] Фича‑флаг per‑company (вкл/выкл).

**DoD:** метрики и быстрый откат (деактивировать источник).

---

## 10) Релиз
- [ ] Прогнать миграции на staging → prod.
- [ ] Права на `var/storage/knowledge` (www-data).
- [ ] Канареечный запуск на 1 компании. Мониторинг latency подсказок (<1.0с).

**DoD:** прод работает, команда пользуется.

---

## Порядок PR (минимум 5)

1. **DB & Migrations** — источники/чанки/feedback + индексы + триггер.
2. **Ingest** — парсер, чанкер, CLI, admin upload minimal.
3. **Search** — `ChunkSearchService` + unit.
4. **Suggestions** — интеграция контекста чанков в подсказки + JSON guard.
5. **UI/Feedback** — Chat Center + Admin preview + feedback API.

---

## Команды/конфиги (шпаргалка)

```bash
# миграции
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate -n

# ingest
php bin/console app:knowledge:ingest var/storage/knowledge/demo.pdf --company=<UUID> --title="Доставка/Оплата" --type=pdf

# dev-окружение
make up && make cache.clear && make yarn.build
```

---

### Бэклог (после MVP)
- `pgvector` + семантический поиск, гибридный ранжирующий запрос.
- LLM re-rank top‑10 → top‑3.
- Автотеги/NER, импорт ZIP с папками, URL‑краулер.
- Панель аналитики: «что спрашивают → чего нет в БЗ».
