# Usage:
# make compile PLUGIN_VERSION=5.0.2

.PHONY: compile
compile:
	bash ./generate-white-label.sh "$(PLUGIN_VERSION)"
