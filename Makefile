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


socket-server-install:
	docker-compose run --rm socket-server yarn install

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
	docker-compose run --rm site-php-cli ./vendor/bin/phpunit --testsuite=unit
	#docker-compose run --rm site-php-cli composer test run unit
	#docker-compose run --rm site-php-cli composer test -- --testsuite=unit
	# unit тесты

site-test-integration:
	# создать БД (если ещё нет)
	docker-compose run --rm site-php-cli \
  	php bin/console doctrine:database:create --if-not-exists --env=test
	# накатить миграции в test-окружении
	docker-compose run --rm site-php-cli \
  	php bin/console doctrine:migrations:migrate --no-interaction --env=test
  	# интеграционные тесты
	docker-compose run --rm site-php-cli ./vendor/bin/phpunit --testsuite=integration


site-test:
	docker-compose run --rm site-php-cli ./vendor/bin/phpunit
	# все тесты

site-wait-db:
	until docker-compose exec -T site-postgres pg_isready --timeout=0 --dbname=app ; do sleep 1 ; done

site-migrations:
	docker-compose run --rm site-php-cli php bin/console doctrine:migrations:migrate --no-interaction

site-fixtures:
	docker-compose run --rm site-php-cli php bin/console doctrine:fixtures:load --no-interaction

build: build-site

build-site:
	docker --log-level=debug build --pull --file=site/docker/production/nginx/Dockerfile --tag=${REGISTRY}/site:${IMAGE_TAG} site
	docker --log-level=debug build --pull --file=site/docker/production/php-fpm/Dockerfile --tag=${REGISTRY}/site-php-fpm:${IMAGE_TAG} site
	docker --log-level=debug build --pull --file=site/docker/production/php-cli/Dockerfile --tag=${REGISTRY}/site-php-cli:${IMAGE_TAG} site

try-build:
	REGISTRY=localhost IMAGE_TAG=0 make build