.PHONY: up down restart logs bash test phpstan pint migrate seed init

DC = docker compose
EXEC_BACKEND = $(DC) exec backend

init:
	cp -n apps/backend/.env.example apps/backend/.env || true
	$(DC) up -d --build
	$(EXEC_BACKEND) composer install
	$(EXEC_BACKEND) php artisan key:generate
	$(EXEC_BACKEND) php artisan migrate --force

up:
	$(DC) up -d

down:
	$(DC) down

restart:
	$(DC) restart

logs:
	$(DC) logs -f

bash:
	$(EXEC_BACKEND) bash

test:
	$(EXEC_BACKEND) ./vendor/bin/pest

test-coverage:
	$(EXEC_BACKEND) ./vendor/bin/pest --coverage

phpstan:
	$(EXEC_BACKEND) ./vendor/bin/phpstan analyse

pint:
	$(EXEC_BACKEND) ./vendor/bin/pint

migrate:
	$(EXEC_BACKEND) php artisan migrate

seed:
	$(EXEC_BACKEND) php artisan db:seed
