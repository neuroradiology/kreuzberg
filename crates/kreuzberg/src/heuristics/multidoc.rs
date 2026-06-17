//! Multi-document PDF boundary detection.
//!
//! Provides heuristics to detect where one document ends and another begins
//! within a single PDF file.  Used for fan-out orchestration of N-document
//! PDFs into N per-document jobs.
//!
//! # Detection rules
//!
//! 1. **Page-one marker** — page N+1's text contains "page 1" or "1 of N" pattern
//!    (case-insensitive) → strong boundary (confidence 0.9).
//! 2. **Letterhead reset** — page N has a signature block AND page N+1 starts with
//!    letterhead-like content → strong boundary (0.85).
//! 3. **Density shift** — adjacent pages differ by `> density_shift_threshold` AND
//!    text excerpts share < 10 % common bigrams → weak boundary (0.5).
//! 4. **No signal** → no boundary.

use std::collections::HashSet;

/// Input signals for multi-document boundary detection.
#[derive(Debug, Clone)]
pub struct MultidocInput {
    /// Total number of pages in the PDF.
    pub page_count: u32,
    /// Per-page signals extracted from the PDF.
    pub pages: Vec<PageSignals>,
}

/// Per-page signals extracted from PDF content.
#[derive(Debug, Clone)]
pub struct PageSignals {
    /// 1-indexed page number.
    pub page_number: u32,
    /// First ~500 characters of extracted text.
    pub text_excerpt: String,
    /// `true` if page starts with letterhead-like content (ALL CAPS line in first 5 lines
    /// or a logo-image bbox at top).
    pub starts_with_letterhead_like: bool,
    /// `true` if text contains "Page 1" or "1 of N" pattern.
    pub has_page_number_one_marker: bool,
    /// `true` if text contains signature indicators ("Sincerely", "Signed") or
    /// a signature image bbox.
    pub has_signature_block: bool,
    /// Text density: characters per page area, normalised to `[0.0, 1.0]`.
    pub layout_text_density: f32,
}

/// Detected document boundary within a PDF.
#[derive(Debug, Clone, PartialEq)]
pub struct DocumentBoundary {
    /// 1-indexed start page (inclusive).
    pub start_page: u32,
    /// 1-indexed end page (inclusive).
    pub end_page: u32,
    /// Confidence in this boundary, `[0.0, 1.0]`.
    pub confidence: f32,
    /// Reason for the boundary detection.
    pub reason: BoundaryReason,
}

/// Reason for boundary detection.
#[derive(Debug, Clone, PartialEq, Eq, Hash)]
pub enum BoundaryReason {
    /// Start of PDF.
    Start,
    /// Page-one marker ("Page 1", "1 of N") detected.
    PageOneMarker,
    /// Letterhead reset after signature block.
    LetterheadReset,
    /// Text density shift with low bigram overlap.
    DensityShift,
    /// End of PDF.
    End,
}

/// Thresholds for multi-document boundary detection.
///
/// All fields are public; callers override any subset via struct-update syntax.
#[derive(Debug, Clone)]
pub struct MultidocThresholds {
    /// Text density difference threshold for `DensityShift` detection.
    /// Default: 0.3.
    pub density_shift_threshold: f32,
    /// Minimum bigram-overlap ratio below which a density shift is promoted to
    /// a `DensityShift` boundary.  Default: 0.1 (10 % overlap).
    pub bigram_overlap_min: f32,
}

impl Default for MultidocThresholds {
    fn default() -> Self {
        Self {
            density_shift_threshold: 0.3,
            bigram_overlap_min: 0.1,
        }
    }
}

/// Detect document boundaries in a multi-document PDF.
///
/// Returns a list of detected boundaries, always including implicit boundaries
/// at start (page 1) and end (page_count).  Boundaries are returned in ascending
/// order of `start_page`.
///
/// # Arguments
///
/// * `input` - Page signals for the PDF
/// * `thresholds` - Detection thresholds
///
/// # Returns
///
/// Ordered list of document boundaries.
pub fn detect_boundaries(input: &MultidocInput, thresholds: &MultidocThresholds) -> Vec<DocumentBoundary> {
    if input.page_count == 0 || input.pages.is_empty() {
        return vec![];
    }

    let mut boundaries = vec![DocumentBoundary {
        start_page: 1,
        end_page: 1,
        confidence: 1.0,
        reason: BoundaryReason::Start,
    }];

    // Detect transitions between consecutive pages.
    for i in 0..input.pages.len().saturating_sub(1) {
        let current = &input.pages[i];
        let next = &input.pages[i + 1];

        if let Some(boundary) = detect_page_transition(current, next, thresholds) {
            boundaries.push(boundary);
        }
    }

    // Add end boundary.
    if input.page_count > 0 {
        boundaries.push(DocumentBoundary {
            start_page: input.page_count,
            end_page: input.page_count,
            confidence: 1.0,
            reason: BoundaryReason::End,
        });
    }

    boundaries
}

/// Detect a boundary between two consecutive pages.
fn detect_page_transition(
    current: &PageSignals,
    next: &PageSignals,
    thresholds: &MultidocThresholds,
) -> Option<DocumentBoundary> {
    // Rule 1: Page-one marker (highest confidence).
    if next.has_page_number_one_marker || has_page_one_pattern(&next.text_excerpt) {
        return Some(DocumentBoundary {
            start_page: next.page_number,
            end_page: next.page_number,
            confidence: 0.9,
            reason: BoundaryReason::PageOneMarker,
        });
    }

    // Rule 2: Letterhead reset after signature.
    if current.has_signature_block && next.starts_with_letterhead_like {
        return Some(DocumentBoundary {
            start_page: next.page_number,
            end_page: next.page_number,
            confidence: 0.85,
            reason: BoundaryReason::LetterheadReset,
        });
    }

    // Rule 3: Density shift with low bigram overlap.
    let density_delta = (current.layout_text_density - next.layout_text_density).abs();
    if density_delta > thresholds.density_shift_threshold {
        let overlap_ratio = compute_bigram_overlap(&current.text_excerpt, &next.text_excerpt);
        if overlap_ratio < thresholds.bigram_overlap_min {
            return Some(DocumentBoundary {
                start_page: next.page_number,
                end_page: next.page_number,
                confidence: 0.5,
                reason: BoundaryReason::DensityShift,
            });
        }
    }

    None
}

/// Compute bigram overlap ratio between two text excerpts.
///
/// Returns a value in `[0.0, 1.0]`; 0.0 = no overlap, 1.0 = identical.
fn compute_bigram_overlap(text_a: &str, text_b: &str) -> f32 {
    let bigrams_a = extract_bigrams(text_a);
    let bigrams_b = extract_bigrams(text_b);

    if bigrams_a.is_empty() || bigrams_b.is_empty() {
        return 0.0;
    }

    let intersection = bigrams_a.intersection(&bigrams_b).count();
    let union_size = bigrams_a.len() + bigrams_b.len() - intersection;

    if union_size == 0 {
        0.0
    } else {
        intersection as f32 / union_size as f32
    }
}

/// Extract bigrams (2-character sequences) from text, lowercased and trimmed.
fn extract_bigrams(text: &str) -> HashSet<String> {
    let normalized = text.to_ascii_lowercase();
    let chars: Vec<char> = normalized.chars().collect();

    (0..chars.len().saturating_sub(1))
        .map(|i| format!("{}{}", chars[i], chars[i + 1]))
        .collect()
}

/// Check if text contains "page 1" or "1 of N" pattern (case-insensitive).
fn has_page_one_pattern(text: &str) -> bool {
    let lower = text.to_ascii_lowercase();
    lower.contains("page 1") || lower.contains("1 of ")
}

#[cfg(test)]
mod tests {
    use super::*;

    fn sample_page(
        page_number: u32,
        text_excerpt: &str,
        starts_with_letterhead_like: bool,
        has_page_number_one_marker: bool,
        has_signature_block: bool,
        layout_text_density: f32,
    ) -> PageSignals {
        PageSignals {
            page_number,
            text_excerpt: text_excerpt.to_string(),
            starts_with_letterhead_like,
            has_page_number_one_marker,
            has_signature_block,
            layout_text_density,
        }
    }

    #[test]
    fn test_single_page_input() {
        let input = MultidocInput {
            page_count: 1,
            pages: vec![sample_page(1, "Hello world", false, false, false, 0.5)],
        };

        let thresholds = MultidocThresholds::default();
        let boundaries = detect_boundaries(&input, &thresholds);

        assert_eq!(boundaries.len(), 2);
        assert_eq!(boundaries[0].reason, BoundaryReason::Start);
        assert_eq!(boundaries[1].reason, BoundaryReason::End);
    }

    #[test]
    fn test_invoice_receipt_scenario() {
        let input = MultidocInput {
            page_count: 5,
            pages: vec![
                sample_page(1, "Invoice #12345. Total: $500", false, false, false, 0.6),
                sample_page(2, "Thank you. Sincerely, John Doe", false, false, true, 0.4),
                sample_page(3, "Receipt. Page 1 of 3. ACME Corp header", true, true, false, 0.7),
                sample_page(4, "Item 1: $10\nItem 2: $20", false, false, false, 0.65),
                sample_page(5, "Total: $30. Thank you", false, false, false, 0.5),
            ],
        };

        let thresholds = MultidocThresholds::default();
        let boundaries = detect_boundaries(&input, &thresholds);

        let page_3_boundaries: Vec<_> = boundaries.iter().filter(|b| b.start_page == 3).collect();
        assert!(!page_3_boundaries.is_empty());

        let strongest = page_3_boundaries.iter().max_by(|a, b| {
            a.confidence
                .partial_cmp(&b.confidence)
                .unwrap_or(std::cmp::Ordering::Equal)
        });
        assert_eq!(strongest.unwrap().reason, BoundaryReason::PageOneMarker);
    }

    #[test]
    fn test_page_one_marker_detection() {
        let input = MultidocInput {
            page_count: 2,
            pages: vec![
                sample_page(1, "First document text", false, false, false, 0.5),
                sample_page(2, "Page 1 of 5. Second document here", false, true, false, 0.6),
            ],
        };

        let thresholds = MultidocThresholds::default();
        let boundaries = detect_boundaries(&input, &thresholds);

        let page_2_boundary = boundaries
            .iter()
            .find(|b| b.start_page == 2)
            .expect("Should detect boundary at page 2");

        assert_eq!(page_2_boundary.reason, BoundaryReason::PageOneMarker);
        assert_eq!(page_2_boundary.confidence, 0.9);
    }

    #[test]
    fn test_letterhead_reset_detection() {
        let input = MultidocInput {
            page_count: 2,
            pages: vec![
                sample_page(1, "Letter content. Sincerely, John", false, false, true, 0.5),
                sample_page(2, "NEW CORP LETTERHEAD. Invoice header", true, false, false, 0.6),
            ],
        };

        let thresholds = MultidocThresholds::default();
        let boundaries = detect_boundaries(&input, &thresholds);

        let page_2_boundary = boundaries
            .iter()
            .find(|b| b.start_page == 2)
            .expect("Should detect boundary at page 2");

        assert_eq!(page_2_boundary.reason, BoundaryReason::LetterheadReset);
        assert_eq!(page_2_boundary.confidence, 0.85);
    }

    #[test]
    fn test_density_shift_detection() {
        let input = MultidocInput {
            page_count: 2,
            pages: vec![
                sample_page(1, "sparse page text", false, false, false, 0.2),
                sample_page(
                    2,
                    "completely different document content that has nothing in common",
                    false,
                    false,
                    false,
                    0.8,
                ),
            ],
        };

        let thresholds = MultidocThresholds::default();
        let boundaries = detect_boundaries(&input, &thresholds);

        let page_2_boundary = boundaries
            .iter()
            .find(|b| b.start_page == 2)
            .expect("Should detect boundary at page 2 due to density shift");

        assert_eq!(page_2_boundary.reason, BoundaryReason::DensityShift);
        assert_eq!(page_2_boundary.confidence, 0.5);
    }

    #[test]
    fn test_no_boundary_with_high_bigram_overlap() {
        let common_text = "The quick brown fox jumps over the lazy dog";
        let input = MultidocInput {
            page_count: 2,
            pages: vec![
                sample_page(1, common_text, false, false, false, 0.5),
                sample_page(2, common_text, false, false, false, 0.8),
            ],
        };

        let thresholds = MultidocThresholds::default();
        let boundaries = detect_boundaries(&input, &thresholds);

        let page_2_density_shift = boundaries
            .iter()
            .find(|b| b.start_page == 2 && b.reason == BoundaryReason::DensityShift);
        assert!(page_2_density_shift.is_none());
    }

    #[test]
    fn test_priority_page_one_over_letterhead() {
        let input = MultidocInput {
            page_count: 2,
            pages: vec![
                sample_page(1, "Letter. Sincerely", false, false, true, 0.5),
                sample_page(2, "Page 1 of 10. CORP HEADER", true, true, false, 0.6),
            ],
        };

        let thresholds = MultidocThresholds::default();
        let boundaries = detect_boundaries(&input, &thresholds);

        let page_2_boundary = boundaries
            .iter()
            .find(|b| b.start_page == 2)
            .expect("Should detect boundary at page 2");

        assert_eq!(page_2_boundary.reason, BoundaryReason::PageOneMarker);
        assert_eq!(page_2_boundary.confidence, 0.9);
    }

    #[test]
    fn test_empty_input() {
        let input = MultidocInput {
            page_count: 0,
            pages: vec![],
        };

        let thresholds = MultidocThresholds::default();
        let boundaries = detect_boundaries(&input, &thresholds);

        assert_eq!(boundaries.len(), 0);
    }

    #[test]
    fn test_bigram_overlap_identical_text() {
        let text = "hello world";
        let overlap = compute_bigram_overlap(text, text);
        assert_eq!(overlap, 1.0);
    }

    #[test]
    fn test_bigram_overlap_completely_different() {
        let text_a = "aaaa";
        let text_b = "bbbb";
        let overlap = compute_bigram_overlap(text_a, text_b);
        assert_eq!(overlap, 0.0);
    }

    #[test]
    fn test_bigram_overlap_partial() {
        let text_a = "hello";
        let text_b = "hella";
        let overlap = compute_bigram_overlap(text_a, text_b);
        // "he", "el", "ll", "lo" vs "he", "el", "ll", "la"
        // intersection: "he", "el", "ll" = 3; union: 4 + 4 - 3 = 5; ratio: 3/5 = 0.6
        assert!(overlap > 0.5 && overlap < 0.7);
    }

    #[test]
    fn test_extract_bigrams() {
        let bigrams = extract_bigrams("ab");
        assert_eq!(bigrams.len(), 1);
        assert!(bigrams.contains("ab"));

        let bigrams = extract_bigrams("abc");
        assert_eq!(bigrams.len(), 2);
        assert!(bigrams.contains("ab"));
        assert!(bigrams.contains("bc"));
    }

    #[test]
    fn test_default_thresholds() {
        let thresholds = MultidocThresholds::default();
        assert_eq!(thresholds.density_shift_threshold, 0.3);
        assert_eq!(thresholds.bigram_overlap_min, 0.1);
    }
}
