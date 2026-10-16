-include .env
HOST_UID := $(shell id -u)
HOST_GID := $(shell id -g)
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

up: .env ## start the local stack on http://localhost:8600
	$(COMPOSE) up -d
	@echo "store: http://localhost:$${WEB_PORT:-8600}  mail: http://localhost:$${MAIL_PORT:-8601}"

vendor/autoload.php: composer.lock
	$(RUN) composer install --no-interaction --no-progress

install: .env vendor/autoload.php ## install wordpress, activate woocommerce and the gateways, enable hpos
	$(RUN) bin/install

console: fonts ## build the operator console (npm run console:dev to watch)
	npm run console

fonts: ## copy the bundled typefaces into the theme
	npm run fonts

seed: .env ## demo catalogue, coupon and customers
	$(RUN) wp storewright seed

pins: .env ## download the India Post PIN directory and import it
	$(RUN) sh -c 'curl -fsSL "$${PINS_SOURCE_URL:-https://raw.githubusercontent.com/dropdevrahul/pincodes-india/master/pincode.csv}" -o /tmp/pincode.csv && wp storewright pins import /tmp/pincode.csv'

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

e2e: .env ## browser tests in a visible chrome window; E2E_SLOWMO=0 for full speed
	npx playwright test

e2e-headless: .env ## browser tests without a window, as ci runs them
	CI=1 npx playwright test

stripe-listen: .env ## forward stripe sandbox webhooks to the local store
	@stripe listen --api-key "$$STRIPE_SECRET_KEY" --all-snapshot --forward-to "http://localhost:$${WEB_PORT:-8600}/wp-json/storewright/v1/webhooks/stripe"

shell: .env ## shell inside the application container
	$(RUN) bash

wp: .env ## run wp-cli, e.g. make wp -- plugin list
	$(RUN) wp $(filter-out $@,$(MAKECMDGOALS))

composer: .env ## run composer, e.g. make composer -- update
	$(RUN) composer $(filter-out $@,$(MAKECMDGOALS))

%:
	@:
