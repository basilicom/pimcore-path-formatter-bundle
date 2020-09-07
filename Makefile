.PHONY: tests
tests:
	vendor/bin/phpunit --configuration ./tests/phpunit.xml --stderr --no-coverage

composer-update:
	docker run --rm -it --env COMPOSER_MEMORY_LIMIT=-1 --volume ${PWD}:/app prooph/composer:7.3 update --ignore-platform-reqs

composer-install:
	docker run --rm -it --env COMPOSER_MEMORY_LIMIT=-1 --volume ${PWD}:/app prooph/composer:7.3 install --ignore-platform-reqs
