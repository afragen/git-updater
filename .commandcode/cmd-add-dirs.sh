#!/usr/bin/env bash
set -euo pipefail

CONFIG=".commandcode/config.json"

if ! command -v jq &>/dev/null; then
  echo "Error: jq is required but not installed." >&2
  exit 1
fi

if [[ ! -f "$CONFIG" ]]; then
  echo "Error: $CONFIG not found." >&2
  exit 1
fi

# Read addDir array, expand ~ to $HOME, build --add-dir flags
ADD_DIR_FLAGS=""
while IFS= read -r dir; do
  dir="${dir/#\~/$HOME}"
  ADD_DIR_FLAGS="$ADD_DIR_FLAGS --add-dir $dir"
done < <(jq -r '.addDir[]' "$CONFIG")

# shellcheck disable=SC2086
exec cmd $ADD_DIR_FLAGS "$@"
