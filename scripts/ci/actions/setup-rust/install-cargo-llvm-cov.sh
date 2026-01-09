#!/usr/bin/env bash
set -euo pipefail

if ! command -v cargo-llvm-cov &>/dev/null; then
  echo "Installing cargo-llvm-cov..."
  cargo install cargo-llvm-cov
else
  echo "cargo-llvm-cov already installed"
fi

# Ensure ~/.cargo/bin is in PATH for subsequent steps
if [[ -n "${GITHUB_PATH:-}" && -d "$HOME/.cargo/bin" ]]; then
  echo "$HOME/.cargo/bin" >>"$GITHUB_PATH"
fi
