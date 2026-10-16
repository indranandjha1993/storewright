-include .env
export
COMPOSE := docker compose --progress quiet
RUN := $(COMPOSE) run --rm cli

.DEFAULT_GOAL := help

help:
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F ':.*## ' '{printf "  %-10s %s\n", $$1, $$2}'

.env:
	@echo ".env is missing; copy .env.example and fill it in" && exit 1

build: .env ## build the application image
	$(COMPOSE) build app

up: .env ## start the local stack on http://localhost:8080
	$(COMPOSE) up -d
	@echo "store: http://localhost:$${WEB_PORT:-8600}  mail: http://localhost:$${MAIL_PORT:-8601}"

install: .env ## install wordpress, activate woocommerce and the gateways, enable hpos
	$(RUN) bin/install

seed: .env ## demo catalogue, coupon and customers
	$(RUN) wp storewright seed

down: ## stop the stack, keep data
	$(COMPOSE) down

reset: ## stop the stack and drop the database volume
	$(COMPOSE) down -v

logs: ## follow application logs
	$(COMPOSE) logs -f app web

lint: .env ## phpcs and phpstan
	$(RUN) composer lint
	$(RUN) composer analyse

test: .env ## unit tests
	$(RUN) composer test

shell: .env ## shell inside the application container
	$(RUN) bash

wp: .env ## run wp-cli, e.g. make wp -- plugin list
	$(RUN) wp $(filter-out $@,$(MAKECMDGOALS))

composer: .env ## run composer, e.g. make composer -- update
	$(RUN) composer $(filter-out $@,$(MAKECMDGOALS))

%:
	@:
