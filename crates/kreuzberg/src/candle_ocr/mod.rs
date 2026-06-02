//! Candle-based VLM OCR backends.
//!
//! Pure-Rust transformer OCR via the `kreuzberg-candle-ocr` crate. This module
//! holds the `OcrBackend + Plugin` impls and the per-model configuration
//! plumbing; model code itself lives in `kreuzberg-candle-ocr::models`.
//!
//! ## Status
//!
//! Phase 3a: TrOCR backend implemented behind `candle-trocr` feature.
//! Phase 3b: PaddleOCR-VL backend implemented behind `candle-paddleocr-vl` feature.

mod config;

#[cfg(feature = "candle-trocr")]
pub mod trocr_backend;

#[cfg(feature = "candle-paddleocr-vl")]
pub mod paddleocr_vl_backend;

pub use config::{CandleModelId, CandleOcrConfig};

#[cfg(feature = "candle-trocr")]
pub use trocr_backend::TrocrBackend;

#[cfg(feature = "candle-paddleocr-vl")]
pub use paddleocr_vl_backend::PaddleOcrVlBackend;
