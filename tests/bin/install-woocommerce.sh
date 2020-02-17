#!/usr/bin/env bash
#
# Install a copy of WooCommerce from source in vendor/woocommerce/woocommerce-src-{version}.
#
# Usage:
#
#   install-wocommerce.sh <version>
#
# Examples:
#
#   # Install woocommerce/woocommerce@master (default behavior)
#   $ install-woocommerce.sh [latest]
#
#   # Install woocommerce/woocommerce@3.7
#   $ install-woocommerce.sh 3.7
#
#   # Show debugging messages
#   $ DEBUG=1 install-woocommerce.sh <version>
#
# Author: Liquid Web
# License: GPLv2 or Later

if [ -n "$DEBUG" ]; then
	set -e
fi

# Print an error message and exit with a non-zero exit code.
error() {
	MESSAGE=${1:-"Something went wrong, aborting."}
	printf "\033[0;31m%s\033[0;0m\n" "$MESSAGE"
	exit 1
}

# Print a debugging message.
debug() {
	if [ -n "$DEBUG" ]; then
		printf "\n\033[0;36m%s\033[0;0m\n" "$1"
	fi
}

WC_VERSION=${1:-latest}
VENDOR_DIR=$(composer config --absolute vendor-dir)
GIT_URL="https://github.com/woocommerce/woocommerce.git"
BRANCH=master

debug "Installing WooCommerce ${WC_VERSION} from source"

# Determine which branch to use, if not master.
if [ "latest" != "$WC_VERSION" ]; then
	# Shorten it to the major/minor version.
	WC_VERSION=$(cut -d . -f1,2 <<< "$WC_VERSION")
	BRANCH="release/${WC_VERSION}"
fi

# See if the version already exists.
TARGET_DIR="${VENDOR_DIR}/woocommerce/woocommerce-src-${WC_VERSION}"

if [[ -d "$TARGET_DIR" ]]; then
	debug "Target ${TARGET_DIR} already exists, aborting."
	exit
fi

debug "Cloning branch '${BRANCH}' from ${GIT_URL}"
git clone --depth 1 --single-branch --branch "$BRANCH" "$GIT_URL" "$TARGET_DIR"\
	|| error "Unable to clone branch ${BRANCH} from ${GIT_URL}"

# Once we've cloned the branch, install any dependencies.
#
# We'll skip over bin/package-update.sh (via --no-scripts), since we don't need to fully-build
# WooCommerce and install all of the necessary JS.
#
# https://github.com/woocommerce/woocommerce/wiki/How-to-set-up-WooCommerce-development-environment
debug "Building WooCommerce ${WC_VERSION} in ${TARGET_DIR}"

composer install -d "$TARGET_DIR" --no-dev --no-suggest --no-interaction --prefer-dist --no-scripts

# The Jetpack autoloader requires a second dump of the autoloader.
composer dump-autoload -d "$TARGET_DIR"

debug "WooCommerce ${BRANCH} has been cached in ${TARGET_DIR}"
