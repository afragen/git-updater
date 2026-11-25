#!/usr/bin/env bash
# Fail on parse errors or compile-time deprecation notices in first-party PHP.
set -uo pipefail

status=0
while IFS= read -r -d '' file; do
	output="$(php -d error_reporting=E_ALL -d display_errors=1 -l "$file" 2>&1)"
	code=$?
	if [ "$code" -ne 0 ] || printf '%s' "$output" | grep -q 'Deprecated:'; then
		printf '%s\n' "$output"
		status=1
	fi
done < <(git ls-files -z -- '*.php' ':(exclude)vendor/*' ':(exclude)node_modules/*')

exit "$status"
