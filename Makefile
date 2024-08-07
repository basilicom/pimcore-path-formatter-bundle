.PHONY: tests
tests:
	vendor/bin/phpunit --configuration ./tests/phpunit.xml --stderr --no-coverage

composer-update:
	docker run --rm -it --env COMPOSER_MEMORY_LIMIT=-1 --volume ${PWD}:/app prooph/composer:8.1 update --ignore-platform-reqs --no-scripts

composer-install:
	docker run --rm -it --env COMPOSER_MEMORY_LIMIT=-1 --volume ${PWD}:/app prooph/composer:8.1 install --ignore-platform-reqs --no-scripts
