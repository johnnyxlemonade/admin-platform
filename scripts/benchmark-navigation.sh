#!/usr/bin/env bash
set -euo pipefail

base_url=${1:?Usage: benchmark-navigation.sh <admin-base-url> <cookie>}
cookie=${2:?Usage: benchmark-navigation.sh <admin-base-url> <cookie>}

measure() {
    local label=$1
    local path=$2
    local headers=$3
    local result
    result=$(curl --silent --show-error --location --output /dev/null --write-out '%{size_download}\t%{time_total}' \
        --cookie "$cookie" \
        $headers \
        "${base_url}${path}")
    printf '%s\t%s\n' "$label" "$result"
}

printf 'route\tbytes\tduration_seconds\n'
for route in / /system/users /system/media; do
    measure "$route" '-H Accept:text/html'
done
