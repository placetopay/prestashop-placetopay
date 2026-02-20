# Usage:
# make compile PLUGIN_VERSION=5.0.3

.PHONY: compile
compile:
	bash ./generate-white-label.sh "$(PLUGIN_VERSION)"
