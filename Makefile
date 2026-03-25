VERSION_PHP=8.4
DOCKER_IMAGE = basilicom/php-fpm-pimcore:11-8.3.21-2
PHP_CS_FIXER = docker run --rm -v $(PWD):/code ghcr.io/php-cs-fixer/php-cs-fixer:3.94.2-php$(VERSION_PHP)
PHP_STAN = docker run --rm -v $(PWD):/app ghcr.io/phpstan/phpstan:2.1.40-php$(VERSION_PHP)

.PHONY: lint
lint: lint-php lint-php-static

.PHONY: lint-fix
lint-fix: lint-php-fix

.PHONY: test
test:
	docker run --rm -it --env COMPOSER_MEMORY_LIMIT=-1 --volume ${PWD}:/php $(DOCKER_IMAGE) /php/vendor/bin/phpunit --configuration ./tests/phpunit.xml --stderr --no-coverage

.PHONY: composer-update
composer-update:
	docker run --rm -it --env COMPOSER_MEMORY_LIMIT=-1 --volume ${PWD}:/php $(DOCKER_IMAGE) composer update --ignore-platform-reqs --no-scripts

.PHONY: composer-instal
composer-install:
	docker run --rm -it --env COMPOSER_MEMORY_LIMIT=-1 --volume ${PWD}:/php $(DOCKER_IMAGE) composer install --ignore-platform-reqs --no-scripts

.PHONY: lint-php
lint-php: ## Lint PHP
	$(PHP_CS_FIXER) fix --dry-run --diff

.PHONY: lint-php-fix
lint-php-fix: ## Lint PHP and fix
	$(PHP_CS_FIXER) fix -vv

.PHONY: lint-php-static
lint-php-static: ## Static code analyse
	$(PHP_STAN) analyse

.PHONY: composer-audit
composer-audit: ## Composer audit checking for security vulnerabilities
	docker run --rm -it --env COMPOSER_MEMORY_LIMIT=-1 --volume ${PWD}:/php $(DOCKER_IMAGE) composer audit
