# Embed API

API встраиваемого виджета позволяет инициализировать веб-чат и отправлять пользовательские сообщения. Ниже описаны два публичных эндпоинта.

## Общие правила

- **Транспорт.** Оба метода принимают `POST`-запросы с телом в формате JSON и заголовком `Content-Type: application/json`.
- **CORS.** При успешной проверке заголовка `Origin` или `page_url` ответ включает `Access-Control-Allow-Origin` со значением разрешённого домена, `Access-Control-Allow-Credentials: true`, `Access-Control-Allow-Headers` (копирует запрошенные заголовки либо использует `Content-Type`) и `Access-Control-Allow-Methods: POST, OPTIONS`. Куки `web_session_id` могут быть отправлены и получены благодаря включённым credentials.
- **Скоростные ограничения.** Для каждого сочетания «сайт + сессия» действует лимит: не более 20 запросов `/api/embed/message` за 10 секунд. Превышение возвращает `429 Too Many Requests` с телом `{"error": "rate limited"}`.

## POST `/api/embed/init`

Инициализирует сессию веб-чата и возвращает идентификаторы, необходимые для подключения к сокетам.

### Тело запроса

```json
{
  "site_key": "string",          // обязательный идентификатор сайта из личного кабинета
  "page_url": "https://..."      // необязательный URL страницы, с которой инициализируется виджет
}
```

### Ответ 200 OK

```json
{
  "session_id": "string",         // уникальный идентификатор сессии; также записывается в cookie web_session_id
  "socket_path": "/socket.io",    // путь для подключения к Socket.IO
  "room": null,                    // комната сокета; для инициализации всегда null
  "policy": {
    "maxTextLen": 2000             // максимальная длина сообщения в символах
  }
}
```

При отсутствии валидного `site_key` или при неразрешённом источнике вернётся `403 Forbidden` с сообщением `{"error": "Invalid site key"}` или `{"error": "Origin not allowed"}` соответственно. Если хранилище не готово — `503 Service Unavailable` с `{"error": "Web chat is not ready"}`. Неизвестный сайт даёт `403` и `{"error": "Site not found"}`.

### Пример cURL

```bash
curl -X POST https://example.com/api/embed/init \
  -H 'Content-Type: application/json' \
  -H 'Origin: https://chat.example.com' \
  -d '{
        "site_key": "site_xxxxx",
        "page_url": "https://chat.example.com/pricing"
      }' \
  -c cookies.txt
```

Флаг `-c` сохранит cookie `web_session_id` для последующих запросов.

## POST `/api/embed/message`

Отправляет текстовое сообщение от пользователя и проксирует его во внутреннюю систему обработки.

### Тело запроса

```json
{
  "site_key": "string",            // обязательный идентификатор сайта
  "text": "string",                // обязательный текст сообщения (1-2000 символов)
  "session_id": "string",          // обязательный, если cookie web_session_id отсутствует
  "page_url": "https://...",       // URL страницы, с которой отправлено сообщение
  "referrer": "https://...",       // реферер страницы (необязательно)
  "utm_source": "...",             // любые utm_* параметры; пустые значения отфильтровываются
  "utm_medium": "...",
  "utm_campaign": "..."
}
```

Минимально необходимое тело — `site_key` и `text`; `session_id` требуется, если cookie не установлена. Текст валидируется на пустоту и максимальную длину (2000 символов).

### Ответ 200 OK

```json
{
  "ok": true,
  "clientId": "string",           // внутренний идентификатор клиента
  "room": "client-<clientId>",    // комната, в которую подписан клиент
  "socket_path": "/socket.io"
}
```

Если сообщение сохранено, контроллер также публикует событие в Redis-канал `chat.client.<clientId>` для realtime-обновления операторского интерфейса.

### Метаданные источника

Каждое входящее сообщение обогащается метаданными `source.*`, доступными обработчикам:

| Поле             | Источник данных                                      |
|------------------|-------------------------------------------------------|
| `source.site_id` | Идентификатор веб-чата (UUID)                         |
| `source.page_url`| Значение `page_url` из запроса                        |
| `source.referrer`| Поле `referrer` из запроса                            |
| `source.utm`     | Словарь с непустыми `utm_*` параметрами из запроса    |
| `source.ip`      | IP-адрес клиента (`Request::getClientIp()`)           |
| `source.ua`      | Заголовок `User-Agent`                                |

Метаданные передаются в сервис `InboundMessage` и доступны вместе с остальной информацией о клиенте и компании.

### Ошибки

- `400 Bad Request`: пустой текст (`{"error": "Invalid message text"}`) или отсутствующая сессия (`{"error": "Invalid session"}`).
- `403 Forbidden`: невалидный ключ сайта либо домен не входит в разрешённые (`{"error": "Invalid site key"}`, `{"error": "Site not found"}`, `{"error": "Origin not allowed"}`).
- `503 Service Unavailable`: хранилище веб-чата не готово (`{"error": "Web chat is not ready"}`).
- `500 Internal Server Error`: сообщение не удалось обработать (`{"error": "Message processing failed"}`).
- `429 Too Many Requests`: сработал лимит частоты (`{"error": "rate limited"}`).

### Пример cURL

```bash
curl -X POST https://example.com/api/embed/message \
  -H 'Content-Type: application/json' \
  -H 'Origin: https://chat.example.com' \
  -b cookies.txt \
  -d '{
        "site_key": "site_xxxxx",
        "text": "Здравствуйте!",
        "page_url": "https://chat.example.com/pricing",
        "referrer": "https://google.com/",
        "utm_source": "adwords",
        "utm_medium": "cpc"
      }'
```

Если cookie с `session_id` отсутствует, добавьте поле `"session_id": "sess_xxxxx"` или опцию `-H 'Cookie: web_session_id=sess_xxxxx'`.
