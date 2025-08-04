init: docker-down-clear site-clear docker-up site-init

docker-up:
	docker-compose up -d
down:
	docker-compose down --remove-orphans
	#docker-compose -f down -v --remove-orphans

docker-down-clear:
	docker-compose down -v --remove-orphans
docker-pull:
	docker-compose pull
docker-build:
	docker-compose build --pull

site-clear:
	docker run --rm -v ${PWD}/site:/app -w /app alpine sh -c 'rm -rf  .ready var/cache/* var/log/* var/test/*'

site-init: site-composer-install site-assets-install site-wait-db site-migrations site-fixtures

site-composer-install:
	docker-compose run --rm site-php-cli composer install

site-assets-install:
	docker-compose run --rm site-node-cli yarn install

site-assets-build:
	docker-compose run --rm site-node-cli yarn build

site-yarn-upgrade:
	docker-compose run --rm site-node-cli yarn upgrade

site-lint:
	docker-compose run --rm site-php-cli composer lint
	docker-compose run --rm site-php-cli composer php-cs-fixer fix -- --dry-run --diff

site-cs-fix:
	docker-compose run --rm site-php-cli composer php-cs-fixer fix

site-test-unit:
	docker-compose run --rm site-php-cli composer test run unit
	docker-compose run --rm site-php-cli composer test -- --testsuite=unit

site-wait-db:
	until docker-compose exec -T site-postgres pg_isready --timeout=0 --dbname=app ; do sleep 1 ; done

site-migrations:
	docker-compose run --rm site-php-cli php bin/console doctrine:migrations:migrate --no-interaction

site-fixtures:
	docker-compose run --rm site-php-cli php bin/console doctrine:fixtures:load --no-interaction


#Запустить Cloudflare Tunnel
tunnel-start:
	docker-compose up -d cloudflared

# Получить HTTPS URL из cloudflared логов и сохранить в .env.local
tunnel-expose:
	@echo "⏳ Получаем публичный URL от cloudflared..."
	@sleep 3
	@docker logs cloudflared 2>&1 | grep -Eo 'https://[a-z0-9-]+\.trycloudflare\.com' | head -n1 | \
    xargs -I{} sh -c 'echo "WEBHOOK_BASE_URL={}" > site/.env.local && echo "✅ URL сохранён: {}"'

# Полная команда: запуск + установка URL
tunnel-init: tunnel-start tunnel-expose

build: build-site

build-site:
	docker --log-level=debug build --pull --file=site/docker/production/nginx/Dockerfile --tag=${REGISTRY}/site:${IMAGE_TAG} site
	docker --log-level=debug build --pull --file=site/docker/production/php-fpm/Dockerfile --tag=${REGISTRY}/site-php-fpm:${IMAGE_TAG} site
	docker --log-level=debug build --pull --file=site/docker/production/php-cli/Dockerfile --tag=${REGISTRY}/site-php-cli:${IMAGE_TAG} site

try-build:
	REGISTRY=localhost IMAGE_TAG=0 make build