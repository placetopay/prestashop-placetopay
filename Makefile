#!/bin/sh

CURRENT_FOLDER=$(shell pwd)
UID=$(shell id -u)
MODULE_NAME=placetopaypayment

# Usage:
# make compile PLUGIN_VERSION=-4.0.6-prestashop-1.7.x PHP_VERSION=7.2
# make compile PLUGIN_VERSION=-4.0.6-prestashop-8.x   PHP_VERSION=7.4

.PHONY: compile
compile:
	$(eval PHP_VERSION=${PHP_VERSION:-7.2})
	$(eval MODULE_NAME_VR=$(MODULE_NAME)$(PLUGIN_VERSION))
	@touch ~/Downloads/placetopaypayment_test \
        && rm -Rf ~/Downloads/placetopaypayment* \
        && cp -pr $(CURRENT_FOLDER) ~/Downloads/placetopaypayment -R \
        && cd ~/Downloads/placetopaypayment \
        && sed -i 's/"php": "[0-9].[0-9].[0-9]"/"php": "$(PHP_VERSION)"/' ~/Downloads/placetopaypayment/composer.json \
        && sed -i 's/"php": "[>=^~].*"/"php": ">=$(PHP_VERSION)"/' ~/Downloads/placetopaypayment/composer.json \
        && rm -Rf ~/Downloads/placetopaypayment/composer.lock \
        && php$(PHP_VERSION) `which composer` install --no-dev \
        && find ~/Downloads/placetopaypayment/ -type d -name ".git*" -exec rm -Rf {} + \
        && find ~/Downloads/placetopaypayment/ -type d -name "squizlabs" -exec rm -Rf {} + \
        && rm -Rf ~/Downloads/placetopaypayment/.git* \
        && rm -Rf ~/Downloads/placetopaypayment/.idea \
        && rm -Rf ~/Downloads/placetopaypayment/config* \
        && rm -Rf ~/Downloads/placetopaypayment/Dockerfile \
        && rm -Rf ~/Downloads/placetopaypayment/Makefile \
        && rm -Rf ~/Downloads/placetopaypayment/.env* \
        && rm -Rf ~/Downloads/placetopaypayment/composer.* \
        && rm -Rf ~/Downloads/placetopaypayment/.php_cs.cache \
        && rm -Rf ~/Downloads/placetopaypayment/*.md \
        && rm -Rf ~/Downloads/placetopaypayment/vendor/bin \
        && rm -Rf ~/Downloads/placetopaypayment/vendor/alejociro/redirection/tests \
        && rm -Rf ~/Downloads/placetopaypayment/vendor/alejociro/redirection/examples \
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
	@echo "Compile file complete: ~/Downloads/$(MODULE_NAME_VR).zip using PHP $(PHP_VERSION)"
