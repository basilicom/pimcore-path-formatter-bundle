.PHONY: tests
tests:
	vendor/bin/phpunit --configuration ./tests/phpunit.xml --stderr --no-coverage
