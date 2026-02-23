//! Deferred result type for async PHP extraction.
//!
//! Provides a pollable result object that PHP code can use to check
//! if an async operation has completed, and retrieve the result when ready.

use ext_php_rs::prelude::*;
use parking_lot::Mutex;
use std::sync::Arc;

use crate::types::ExtractionResult;

/// Internal state for a deferred extraction result.
///
/// Wraps either a single result or a batch of results (for batch operations).
#[allow(clippy::large_enum_variant)]
pub(crate) enum DeferredInner {
    Single(Option<Result<kreuzberg::ExtractionResult, String>>),
    Batch(Option<Result<Vec<kreuzberg::ExtractionResult>, String>>),
}

/// A deferred result from an async extraction operation.
///
/// This object is returned immediately from async extraction functions.
/// The actual extraction runs on a background Tokio worker thread.
///
/// # PHP Usage
///
/// ```php
/// $deferred = kreuzberg_extract_file_async('document.pdf');
///
/// // Non-blocking check
/// if ($deferred->isReady()) {
///     $result = $deferred->getResult();
/// }
///
/// // Blocking wait
/// $result = $deferred->getResult();
///
/// // Wait with timeout (milliseconds)
/// $result = $deferred->wait(5000);
/// ```
#[php_class]
#[php(name = "Kreuzberg\\Types\\DeferredResult")]
pub struct DeferredResult {
    inner: Arc<Mutex<DeferredInner>>,
    extract_tables: bool,
}

#[php_impl]
impl DeferredResult {
    /// Check if the result is ready (non-blocking).
    ///
    /// # Returns
    ///
    /// `true` if the result is available, `false` if still processing.
    #[php(name = "isReady")]
    pub fn is_ready(&self) -> bool {
        let guard = self.inner.lock();
        match &*guard {
            DeferredInner::Single(slot) => slot.is_some(),
            DeferredInner::Batch(slot) => slot.is_some(),
        }
    }

    /// Try to get the result without blocking.
    ///
    /// # Returns
    ///
    /// The `ExtractionResult` if ready, or `null` if still processing.
    ///
    /// # Throws
    ///
    /// Exception if the extraction failed.
    #[php(name = "tryGetResult")]
    pub fn try_get_result(&self) -> PhpResult<Option<ExtractionResult>> {
        let guard = self.inner.lock();
        match &*guard {
            DeferredInner::Single(Some(result)) => match result {
                Ok(r) => Ok(Some(ExtractionResult::from_rust_with_config(
                    r.clone(),
                    self.extract_tables,
                )?)),
                Err(e) => Err(PhpException::default(e.clone())),
            },
            DeferredInner::Single(None) => Ok(None),
            DeferredInner::Batch(_) => Err(PhpException::default(
                "Use tryGetResults() for batch operations".to_string(),
            )),
        }
    }

    /// Get the result, blocking until it's ready.
    ///
    /// # Returns
    ///
    /// The `ExtractionResult` from the async extraction.
    ///
    /// # Throws
    ///
    /// Exception if the extraction failed or timed out.
    #[php(name = "getResult")]
    pub fn get_result(&self) -> PhpResult<ExtractionResult> {
        // Busy-wait with short sleeps until result is available
        loop {
            {
                let guard = self.inner.lock();
                match &*guard {
                    DeferredInner::Single(Some(result)) => {
                        return match result {
                            Ok(r) => ExtractionResult::from_rust_with_config(r.clone(), self.extract_tables),
                            Err(e) => Err(PhpException::default(e.clone())),
                        };
                    }
                    DeferredInner::Batch(_) => {
                        return Err(PhpException::default(
                            "Use getResults() for batch operations".to_string(),
                        ));
                    }
                    _ => {}
                }
            }
            std::thread::sleep(std::time::Duration::from_millis(1));
        }
    }

    /// Get the batch results, blocking until ready.
    ///
    /// # Returns
    ///
    /// Array of `ExtractionResult` from the async batch extraction.
    ///
    /// # Throws
    ///
    /// Exception if the extraction failed.
    #[php(name = "getResults")]
    pub fn get_results(&self) -> PhpResult<Vec<ExtractionResult>> {
        loop {
            {
                let guard = self.inner.lock();
                match &*guard {
                    DeferredInner::Batch(Some(result)) => {
                        return match result {
                            Ok(results) => results
                                .iter()
                                .map(|r| ExtractionResult::from_rust_with_config(r.clone(), self.extract_tables))
                                .collect(),
                            Err(e) => Err(PhpException::default(e.clone())),
                        };
                    }
                    DeferredInner::Single(_) => {
                        return Err(PhpException::default(
                            "Use getResult() for single operations".to_string(),
                        ));
                    }
                    _ => {}
                }
            }
            std::thread::sleep(std::time::Duration::from_millis(1));
        }
    }

    /// Wait for the result with a timeout (in milliseconds).
    ///
    /// # Parameters
    ///
    /// - `timeout_ms` (int): Maximum time to wait in milliseconds
    ///
    /// # Returns
    ///
    /// The `ExtractionResult` if ready within timeout, or `null` if timed out.
    ///
    /// # Throws
    ///
    /// Exception if the extraction failed.
    pub fn wait(&self, timeout_ms: i64) -> PhpResult<Option<ExtractionResult>> {
        let deadline = std::time::Instant::now() + std::time::Duration::from_millis(timeout_ms as u64);

        loop {
            {
                let guard = self.inner.lock();
                match &*guard {
                    DeferredInner::Single(Some(result)) => {
                        return match result {
                            Ok(r) => Ok(Some(ExtractionResult::from_rust_with_config(
                                r.clone(),
                                self.extract_tables,
                            )?)),
                            Err(e) => Err(PhpException::default(e.clone())),
                        };
                    }
                    DeferredInner::Batch(_) => {
                        return Err(PhpException::default(
                            "Use waitBatch() for batch operations".to_string(),
                        ));
                    }
                    _ => {}
                }
            }

            if std::time::Instant::now() >= deadline {
                return Ok(None);
            }

            std::thread::sleep(std::time::Duration::from_millis(1));
        }
    }

    /// Wait for batch results with a timeout (in milliseconds).
    ///
    /// # Parameters
    ///
    /// - `timeout_ms` (int): Maximum time to wait in milliseconds
    ///
    /// # Returns
    ///
    /// Array of `ExtractionResult` if ready within timeout, or `null` if timed out.
    ///
    /// # Throws
    ///
    /// Exception if the extraction failed.
    #[php(name = "waitBatch")]
    pub fn wait_batch(&self, timeout_ms: i64) -> PhpResult<Option<Vec<ExtractionResult>>> {
        let deadline = std::time::Instant::now() + std::time::Duration::from_millis(timeout_ms as u64);

        loop {
            {
                let guard = self.inner.lock();
                match &*guard {
                    DeferredInner::Batch(Some(result)) => {
                        return match result {
                            Ok(results) => Ok(Some(
                                results
                                    .iter()
                                    .map(|r| ExtractionResult::from_rust_with_config(r.clone(), self.extract_tables))
                                    .collect::<PhpResult<Vec<_>>>()?,
                            )),
                            Err(e) => Err(PhpException::default(e.clone())),
                        };
                    }
                    DeferredInner::Single(_) => {
                        return Err(PhpException::default("Use wait() for single operations".to_string()));
                    }
                    _ => {}
                }
            }

            if std::time::Instant::now() >= deadline {
                return Ok(None);
            }

            std::thread::sleep(std::time::Duration::from_millis(1));
        }
    }
}

impl DeferredResult {
    /// Create a new single-result deferred with a shared slot.
    pub(crate) fn new_single(inner: Arc<Mutex<DeferredInner>>, extract_tables: bool) -> Self {
        Self { inner, extract_tables }
    }

    /// Create a new batch-result deferred with a shared slot.
    pub(crate) fn new_batch(inner: Arc<Mutex<DeferredInner>>, extract_tables: bool) -> Self {
        Self { inner, extract_tables }
    }
}
