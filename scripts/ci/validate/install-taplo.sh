#!/usr/bin/env bash
set -euo pipefail

bash scripts/ci/validate/show-disk-space.sh "Before taplo installation"

if ! command -v taplo >/dev/null 2>&1; then
  cargo install taplo-cli --locked
fi

# Ensure ~/.cargo/bin is in PATH for subsequent steps
if [[ -n "${GITHUB_PATH:-}" && -d "$HOME/.cargo/bin" ]]; then
  echo "$HOME/.cargo/bin" >>"$GITHUB_PATH"
fi

rm -rf ~/.cargo/registry/cache/* ~/.cargo/git/db/* 2>/dev/null || true

bash scripts/ci/validate/show-disk-space.sh "After taplo installation and cleanup"
