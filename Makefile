#!/bin/sh

CONTAINER_PS=plugin_ps_prestashop
CONTAINER_DB=plugin_ps_database
CURRENT_FOLDER=$(shell pwd)
UID=$(shell id -u)
APACHE_USER=www-data
MODULE_NAME=placetopaypayment

# Persistence commands

.PHONY: config
config: restore-override
	@docker-compose config

.PHONY: up
up: restore-override
	@docker-compose up -d

.PHONY: down
down: restore-override
	@docker-compose down -v

.PHONY: restart
restart: dev-down restore-override down up

.PHONY: rebuild
rebuild: dev-down restore-override down
	@docker-compose up -d --build
	@make install

.PHONY: install
install: dev-down restore-override down up set-acl composer-update
	@echo "That is all!"

# Development commands

.PHONY: dev-config
dev-config: move-override
	@docker-compose config

.PHONY: dev-up
dev-up: move-override
	@docker-compose up -d

.PHONY: dev-down
dev-down: move-override
	@docker-compose down -v

.PHONY: dev-restart
dev-restart: down move-override dev-down dev-up

.PHONY: dev-rebuild
dev-rebuild: down move-override dev-down
	@docker-compose up -d --build
	@make dev-install

.PHONY: dev-install
dev-install: down move-override dev-down dev-up set-acl composer-update
	@echo "That is all!"

# Generic commands

.PHONY: set-acl
set-acl: set-acl-unix

.PHONY: set-acl-unix
set-acl-unix:
	@echo "Verifing ACL folder"
	@if [ -z "$(shell getfacl -acp $(CURRENT_FOLDER) | grep $(APACHE_USER))" ]; \
	then \
		sudo setfacl -dR -m u:$(APACHE_USER):rwX -m u:`whoami`:rwX $(CURRENT_FOLDER); \
		sudo setfacl -R -m u:$(APACHE_USER):rwX -m u:`whoami`:rwX $(CURRENT_FOLDER); \
	fi;

.PHONY: start
start: start-database start-prestashop

.PHONY: stop
stop: stop-prestashop stop-database

.PHONY: start-prestashop
start-prestashop:
	@docker container start $(CONTAINER_PS)

.PHONY: stop-prestashop
stop-prestashop:
	@docker container stop $(CONTAINER_PS)

.PHONY: start-database
start-database:
	@docker container start $(CONTAINER_DB)

.PHONY: stop-database
stop-database:
	@docker container stop $(CONTAINER_DB)

.PHONY: bash-prestashop
bash-prestashop:
	@docker exec -u root -it $(CONTAINER_PS) bash

.PHONY: bash-database
bash-database:
	@docker exec -u root -it $(CONTAINER_DB) bash

.PHONY: logs-prestashop
logs-prestashop:
	@docker logs $(CONTAINER_PS) -f

.PHONY: logs-database
logs-database:
	@docker logs $(CONTAINER_DB) -f

.PHONY: composer-update
composer-update:
	@composer update

# Utils commands

.PHONY: move-override
move-override:
	@if [ -e docker-compose.override.yml ]; \
	then \
		mv docker-compose.override.yml docker-compose.backup.yml; \
	fi;

.PHONY: restore-override
restore-override:
	@if [ -e docker-compose.backup.yml ]; \
	then \
		mv docker-compose.backup.yml docker-compose.override.yml; \
	fi;

.PHONY: compile
compile:
	$(eval MODULE_NAME_VR=$(MODULE_NAME)$(PLUGIN_VERSION))
	@touch ~/Downloads/placetopaypayment_test \
        && rm -Rf ~/Downloads/placetopaypayment* \
        && cp -p $(CURRENT_FOLDER) ~/Downloads/placetopaypayment -R \
        && cd ~/Downloads/placetopaypayment \
        && composer install --no-dev \
        && find ~/Downloads/placetopaypayment/ -type d -name ".git*" -exec rm -Rf {} + \
        && find ~/Downloads/placetopaypayment/ -type d -name "squizlabs" -exec rm -Rf {} + \
        && rm -Rf ~/Downloads/placetopaypayment/.git* \
        && rm -Rf ~/Downloads/placetopaypayment/.idea \
        && rm -Rf ~/Downloads/placetopaypayment/config* \
        && rm -Rf ~/Downloads/placetopaypayment/Dockerfile \
        && rm -Rf ~/Downloads/placetopaypayment/Makefile \
        && rm -Rf ~/Downloads/placetopaypayment/.env* \
        && rm -Rf ~/Downloads/placetopaypayment/docker* \
        && rm -Rf ~/Downloads/placetopaypayment/composer.* \
        && rm -Rf ~/Downloads/placetopaypayment/.php_cs.cache \
        && rm -Rf ~/Downloads/placetopaypayment/*.md \
        && rm -Rf ~/Downloads/placetopaypayment/vendor/bin \
        && rm -Rf ~/Downloads/placetopaypayment/vendor/dnetix/redirection/tests \
        && rm -Rf ~/Downloads/placetopaypayment/vendor/dnetix/redirection/examples \
        && rm -Rf ~/Downloads/placetopaypayment/vendor/guzzlehttp/ringphp/docs \
        && rm -Rf ~/Downloads/placetopaypayment/vendor/guzzlehttp/ringphp/tests \
        && rm -Rf ~/Downloads/placetopaypayment/vendor/guzzlehttp/guzzle/docs \
        && rm -Rf ~/Downloads/placetopaypayment/vendor/guzzlehttp/guzzle/tests \
        && rm -Rf ~/Downloads/placetopaypayment/vendor/guzzlehttp/streams/tests \
        && cd ~/Downloads \
        && zip -r -q -o $(MODULE_NAME_VR).zip placetopaypayment \
        && chown $(UID):$(UID) $(MODULE_NAME_VR).zip \
        && chmod 644 $(MODULE_NAME_VR).zip \
        && rm -Rf ~/Downloads/placetopaypayment
	@echo "Compile file complete: ~/Downloads/$(MODULE_NAME_VR).zip"
