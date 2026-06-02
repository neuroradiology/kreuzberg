# kreuzberg-candle-ocr

Candle-based VLM OCR engines for Kreuzberg. Pure-Rust transformer OCR via [candle](https://github.com/huggingface/candle).

Supported models (per-model sub-features):

- **trocr** — Microsoft TrOCR (printed/handwritten, ~330M, MIT).
- **paddleocr-vl** — PaddleOCR-VL (0.9B, Apache-2.0, multi-task: OCR/tables/formulas/charts).

Device pass-through features mirror candle's own: `cuda`, `metal`, `mkl`, `accelerate`.
