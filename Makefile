.PHONY: setup dev test analyse refactor-dry metrics quality build e2e

setup:
	composer install
	npm install
	php artisan key:generate
	php artisan migrate --force
	npm run build

dev:
	composer dev

test:
	composer test

analyse:
	composer analyse

refactor-dry:
	composer refactor:dry

metrics:
	composer metrics

quality:
	composer quality
	npm run build

build:
	npm run build

e2e:
	npm run test:e2e
