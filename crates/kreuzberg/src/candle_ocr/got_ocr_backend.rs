//! GOT-OCR 2.0 backend plugin for the Kreuzberg OCR pipeline.
//!
//! This module wraps the candle-based GOT-OCR 2.0 engine in the `OcrBackend` trait,
//! making it available to the extraction pipeline.

use async_trait::async_trait;
use std::borrow::Cow;
use std::path::Path;
#[cfg(not(target_arch = "wasm32"))]
use std::sync::{Arc, LazyLock};

#[cfg(not(target_arch = "wasm32"))]
use ahash::AHashMap;
#[cfg(not(target_arch = "wasm32"))]
use parking_lot::RwLock;

use crate::Result;
use crate::core::config::OcrConfig;
use crate::plugins::{OcrBackend, OcrBackendType, Plugin};
use crate::types::ExtractionResult;
#[cfg(not(target_arch = "wasm32"))]
use kreuzberg_candle_ocr::DType;
use kreuzberg_candle_ocr::DevicePreference;
use kreuzberg_candle_ocr::models::GotOcrMode;
#[cfg(not(target_arch = "wasm32"))]
use kreuzberg_candle_ocr::models::GotOcrEngine;

/// Process-wide engine pool keyed by `(mode, device_preference)`.
///
/// Engines are expensive to initialise (~1.5 GB of safetensors weights).
/// The pool ensures each `(mode, device)` combination is loaded at
/// most once per process.
///
/// `DevicePreference` already carries the canonical candle device taxonomy
/// (`Auto | Cpu | Cuda | Metal`); we reuse it directly as the key rather than
/// inventing a parallel enum.
#[cfg(not(target_arch = "wasm32"))]
static ENGINE_POOL: LazyLock<RwLock<AHashMap<(GotOcrMode, DevicePreference), Arc<GotOcrEngine>>>> =
    LazyLock::new(|| RwLock::new(AHashMap::new()));

/// Return a cached engine for `(mode, preference)`, initialising one on first use.
///
/// Uses a read → miss → write → double-check pattern so that two racing callers
/// do not both pay the initialisation cost.
#[cfg(not(target_arch = "wasm32"))]
fn get_or_init_engine(
    mode: GotOcrMode,
    preference: DevicePreference,
) -> crate::Result<Arc<GotOcrEngine>> {
    let key = (mode, preference);

    // Fast path: engine already in pool.
    {
        let pool = ENGINE_POOL.read();
        if let Some(engine) = pool.get(&key) {
            return Ok(Arc::clone(engine));
        }
    }

    // Slow path: select the device and build the engine, then insert under write lock.
    let device = preference.select().map_err(|e| crate::KreuzbergError::Ocr {
        message: format!("Failed to select compute device: {e}"),
        source: None,
    })?;

    tracing::info!(mode = ?mode, preference = ?preference, "Initialising GOT-OCR 2.0 engine (cold start)");
    let new_engine = GotOcrEngine::new(mode, device, DType::F32).map_err(|e| {
        crate::KreuzbergError::Ocr {
            message: format!("GOT-OCR 2.0 engine initialisation failed: {e}"),
            source: None,
        }
    })?;
    let new_engine = Arc::new(new_engine);

    let mut pool = ENGINE_POOL.write();
    // Double-check: another thread may have inserted while we were building.
    if let Some(existing) = pool.get(&key) {
        return Ok(Arc::clone(existing));
    }
    pool.insert(key, Arc::clone(&new_engine));
    Ok(new_engine)
}

/// GOT-OCR 2.0 backend using candle transformers.
///
/// A lightweight vision-language model for structured document OCR combining
/// SAM ViT-Det vision encoder with Qwen-0.5B text decoder. Emits markdown
/// for tables, formulas, and complex layouts.
///
/// # Configuration
///
/// GOT-OCR 2.0 accepts backend options for output mode selection:
/// ```json
/// {
///   "mode": "formatted",
///   "device": "auto"
/// }
/// ```
///
/// - `mode` (string): `"plain"` (default) or `"formatted"` (markdown output)
/// - `device` (string): `"auto"`, `"cpu"`, `"cuda"`, `"metal"`
#[cfg_attr(alef, alef(skip))]
pub struct GotOcrBackend {
    mode: GotOcrMode,
}

impl GotOcrBackend {
    /// Create a new GOT-OCR 2.0 backend with the specified output mode.
    pub fn new(mode: GotOcrMode) -> Self {
        Self { mode }
    }

    /// Create a GOT-OCR 2.0 backend with the default mode (PlainText).
    pub fn default_mode() -> Self {
        Self::new(GotOcrMode::default())
    }

    /// Parse backend options to extract GOT-OCR-specific configuration.
    ///
    /// Device selection is delegated to [`crate::candle_ocr::resolve_device_preference`]
    /// so the central `AccelerationConfig` is honoured.
    fn parse_options(config: &OcrConfig) -> (GotOcrMode, DevicePreference) {
        let mut mode = GotOcrMode::default();

        if let Some(opts) = &config.backend_options
            && let Some(m) = opts.get("mode").and_then(|v| v.as_str())
        {
            mode = match m {
                "formatted" => GotOcrMode::Formatted,
                _ => GotOcrMode::PlainText, // default on unknown
            };
        }

        let device = super::resolve_device_preference(config);
        (mode, device)
    }
}

impl Plugin for GotOcrBackend {
    fn name(&self) -> &str {
        "candle-got-ocr"
    }

    fn version(&self) -> String {
        "0.1.0".to_string()
    }

    fn initialize(&self) -> Result<()> {
        tracing::debug!("Initializing GOT-OCR 2.0 backend: {} mode", self.mode);
        Ok(())
    }

    fn shutdown(&self) -> Result<()> {
        Ok(())
    }
}

#[async_trait]
impl OcrBackend for GotOcrBackend {
    async fn process_image(&self, image_bytes: &[u8], config: &OcrConfig) -> Result<ExtractionResult> {
        // Parse configuration
        let (mode, device) = Self::parse_options(config);

        // Validate image data
        if image_bytes.is_empty() {
            return Err(crate::KreuzbergError::Validation {
                message: "Empty image data provided to GOT-OCR 2.0".to_string(),
                source: None,
            });
        }

        // Clone image bytes for async block
        let image_bytes = image_bytes.to_vec();

        // Run inference in a blocking task to avoid blocking the async runtime
        let content = tokio::task::spawn_blocking(move || {
            // Retrieve a cached engine or initialise one on first use.
            // Device selection happens inside get_or_init_engine on first call;
            // subsequent calls for the same (mode, device) reuse the pooled engine.
            let engine = get_or_init_engine(mode, device)?;

            // Process image through encoder-decoder pipeline
            let output = engine
                .process_image(&image_bytes)
                .map_err(|e| crate::KreuzbergError::Ocr {
                    message: format!("GOT-OCR 2.0 inference failed: {}", e),
                    source: None,
                })?;

            Ok::<String, crate::KreuzbergError>(output.content)
        })
        .await
        .map_err(|e| crate::KreuzbergError::Ocr {
            message: format!("GOT-OCR 2.0 task execution failed: {}", e),
            source: None,
        })??;

        // Determine MIME type based on output mode
        let mime_type = match mode {
            GotOcrMode::Formatted => "text/markdown",
            GotOcrMode::PlainText => "text/plain",
        };

        Ok(ExtractionResult {
            content,
            mime_type: Cow::Borrowed(mime_type),
            ..Default::default()
        })
    }

    async fn process_image_file(&self, path: &Path, config: &OcrConfig) -> Result<ExtractionResult> {
        let bytes = crate::core::io::read_file_async(path).await?;
        self.process_image(&bytes, config).await
    }

    fn supports_language(&self, _lang: &str) -> bool {
        // GOT-OCR 2.0 supports English and Chinese with graceful fallback to other languages.
        // For simplicity, accept all language codes.
        true
    }

    fn supported_languages(&self) -> Vec<String> {
        // Primary languages supported by GOT-OCR 2.0
        vec![
            "eng", "en", // English
            "zho", "zh", // Chinese (simplified and traditional)
        ]
        .iter()
        .map(|s| s.to_string())
        .collect()
    }

    fn backend_type(&self) -> OcrBackendType {
        OcrBackendType::Candle
    }

    fn emits_structured_markdown(&self) -> bool {
        // GOT-OCR 2.0 emits markdown output in formatted mode,
        // so the extraction pipeline should skip layout reconstruction stages.
        matches!(self.mode, GotOcrMode::Formatted)
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_got_ocr_backend_creation() {
        let backend = GotOcrBackend::default_mode();
        assert_eq!(backend.name(), "candle-got-ocr");
        assert_eq!(backend.backend_type(), OcrBackendType::Candle);
    }

    #[test]
    fn test_got_ocr_emits_structured_markdown_when_formatted() {
        let backend = GotOcrBackend::new(GotOcrMode::Formatted);
        assert!(backend.emits_structured_markdown());
    }

    #[test]
    fn test_got_ocr_plain_text_not_markdown() {
        let backend = GotOcrBackend::new(GotOcrMode::PlainText);
        assert!(!backend.emits_structured_markdown());
    }

    #[test]
    fn test_got_ocr_language_support() {
        let backend = GotOcrBackend::default_mode();
        // Should support primary languages
        assert!(backend.supports_language("eng"));
        assert!(backend.supports_language("zho"));
        // Should also support unknown codes (accept all)
        assert!(backend.supports_language("unknown"));
    }

    #[test]
    fn test_got_ocr_supported_languages() {
        let backend = GotOcrBackend::default_mode();
        let langs = backend.supported_languages();
        assert!(langs.contains(&"eng".to_string()));
        assert!(langs.contains(&"zho".to_string()));
    }

    #[test]
    fn test_parse_options_defaults() {
        let config = OcrConfig::default();
        let (mode, device) = GotOcrBackend::parse_options(&config);
        assert_eq!(mode, GotOcrMode::PlainText);
        assert_eq!(device, DevicePreference::Auto);
    }

    #[test]
    fn test_parse_options_custom_mode() {
        let mut config = OcrConfig::default();
        config.backend_options = Some(serde_json::json!({
            "mode": "formatted"
        }));
        let (mode, _device) = GotOcrBackend::parse_options(&config);
        assert_eq!(mode, GotOcrMode::Formatted);
    }

    #[test]
    fn test_parse_options_custom_device() {
        let mut config = OcrConfig::default();
        config.backend_options = Some(serde_json::json!({
            "device": "cpu"
        }));
        let (_mode, device) = GotOcrBackend::parse_options(&config);
        assert_eq!(device, DevicePreference::Cpu);
    }

    #[test]
    fn test_initialize_and_shutdown() {
        let backend = GotOcrBackend::default_mode();
        assert!(backend.initialize().is_ok());
        assert!(backend.shutdown().is_ok());
    }
}
