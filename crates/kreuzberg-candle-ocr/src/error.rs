use thiserror::Error;

#[derive(Debug, Error)]
pub enum CandleOcrError {
    #[cfg(not(target_arch = "wasm32"))]
    #[error("candle error: {0}")]
    Candle(#[from] candle_core::Error),

    #[error("tokenizer error: {0}")]
    Tokenizer(String),

    #[error("image error: {0}")]
    Image(#[from] image::ImageError),

    #[error("io error: {0}")]
    Io(#[from] std::io::Error),

    #[error("model load failed: {0}")]
    ModelLoadFailed(String),

    #[error("inference failed: {0}")]
    InferenceFailed(String),

    #[error("unsupported configuration: {0}")]
    UnsupportedConfig(String),
}

pub type Result<T> = std::result::Result<T, CandleOcrError>;
