#!/usr/bin/env bash
#
# Local head-to-head PDF benchmark: Xberg (heuristics + routed layout) vs
# LiteParse (and Docling, if installed). Establishes the Phase-0 baseline —
# per-document quality with a gap report — that every later change is measured
# against.
#
# It runs two modes:
#   single-file : per-document quality (TF1/SF1/combined) + cold start + single
#                 file throughput. This feeds the gap report.
#   batch       : batch throughput. NOTE: the harness runs Xberg's batch as
#                 concurrent per-file `xberg extract` spawns (each re-initialises
#                 the CLI + models), while LiteParse uses a single native
#                 `lit batch-parse` (load once). That asymmetry is itself under
#                 investigation as part of the batch-throughput fix — the numbers
#                 here are the baseline for that work.
#
# Environment overrides:
#   FIXTURES     fixtures dir (default: the 177-fixture PDF corpus)
#   OUT          output dir   (default: tools/benchmark-harness/results/local)
#   FRAMEWORKS   single-file frameworks (default: xberg baseline+layout, liteparse)
#   ITERATIONS   iterations per doc (default: 1)
#   TIMEOUT      per-extraction timeout seconds (default: 300)
#   SHARD        run a subset, e.g. "1/60" for a quick smoke run (default: none)
#   SKIP_BUILD   set to 1 to skip the cargo builds (default: build)
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$REPO_ROOT"

FIXTURES="${FIXTURES:-tools/benchmark-harness/fixtures/pdf}"
OUT="${OUT:-tools/benchmark-harness/results/local}"
FRAMEWORKS="${FRAMEWORKS:-xberg-markdown-baseline,xberg-markdown-layout,liteparse}"
ITERATIONS="${ITERATIONS:-1}"
TIMEOUT="${TIMEOUT:-300}"
SHARD="${SHARD:-}"

# 1. Ensure LiteParse's `lit` is on PATH.
if ! command -v lit >/dev/null 2>&1; then
  for cand in /tmp/liteparse/target/release ../liteparse/target/release; do
    if [ -x "$cand/lit" ]; then
      export PATH="$cand:$PATH"
      break
    fi
  done
fi
if command -v lit >/dev/null 2>&1; then
  echo "[bench:local] lit: $(command -v lit) ($(lit --version 2>/dev/null))"
else
  echo "[bench:local] WARN: lit not found — liteparse rows will be skipped." >&2
fi

# 2. Build the xberg CLI + harness (release), unless skipped.
if [ "${SKIP_BUILD:-0}" != "1" ]; then
  echo "[bench:local] Building xberg CLI (release, --features all)…"
  cargo build --locked --release -p xberg-cli --features all
  echo "[bench:local] Building benchmark harness (release)…"
  cargo build --locked --release -p benchmark-harness
fi
HARNESS=./target/release/benchmark-harness

# 3. Add Docling only if it is importable locally.
if python3 -c "import docling" >/dev/null 2>&1; then
  echo "[bench:local] docling detected — including it."
  FRAMEWORKS="$FRAMEWORKS,docling"
else
  echo "[bench:local] docling not installed — skipping (study its output from /tmp/docling)."
fi

SHARD_ARGS=()
[ -n "$SHARD" ] && SHARD_ARGS=(--shard "$SHARD")

# 4. Single-file run → per-document quality, cold start, single-file throughput.
echo "[bench:local] === single-file run: $FRAMEWORKS ==="
mkdir -p "$OUT/single"
"$HARNESS" run \
  --fixtures "$FIXTURES" \
  --frameworks "$FRAMEWORKS" \
  --output "$OUT/single" \
  --mode single-file \
  --max-concurrent 1 \
  --iterations "$ITERATIONS" \
  --timeout "$TIMEOUT" \
  --measure-quality \
  --output-format markdown \
  "${SHARD_ARGS[@]}"

# 5. Batch run → batch throughput (xberg vs liteparse; docling has no batch API).
echo "[bench:local] === batch run ==="
mkdir -p "$OUT/batch"
"$HARNESS" run \
  --fixtures "$FIXTURES" \
  --frameworks "xberg-markdown-baseline,xberg-markdown-layout,liteparse" \
  --output "$OUT/batch" \
  --mode batch \
  --iterations "$ITERATIONS" \
  --timeout "$TIMEOUT" \
  --measure-quality \
  --output-format markdown \
  "${SHARD_ARGS[@]}"

# 6. Per-document gap report on the single-file results.
echo "[bench:local] === gap report ==="
"$HARNESS" gap-report --results "$OUT/single" --output "$OUT/single"

echo ""
echo "[bench:local] Done."
echo "[bench:local]   single-file results : $OUT/single/results.json"
echo "[bench:local]   batch results       : $OUT/batch/results.json"
echo "[bench:local]   per-document pivot   : $OUT/single/per_document.json"
echo "[bench:local]   gap report           : $OUT/single/gaps.md"
