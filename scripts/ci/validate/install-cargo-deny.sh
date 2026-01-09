#!/usr/bin/env bash
set -euo pipefail

bash scripts/ci/validate/show-disk-space.sh "Before cargo-deny installation"

cargo install cargo-deny --locked

# Ensure ~/.cargo/bin is in PATH for subsequent steps
if [[ -n "${GITHUB_PATH:-}" && -d "$HOME/.cargo/bin" ]]; then
  echo "$HOME/.cargo/bin" >>"$GITHUB_PATH"
fi

rm -rf ~/.cargo/registry/cache/* ~/.cargo/git/db/* 2>/dev/null || true

bash scripts/ci/validate/show-disk-space.sh "After cargo-deny installation and cleanup"
