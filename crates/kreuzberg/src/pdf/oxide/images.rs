//! Image extraction using the pdf_oxide backend.
//!
//! Extracts embedded images from PDF pages via pdf_oxide, mapping them to
//! Kreuzberg's `PdfImage` type. Handles JPEG passthrough and Raw→PNG encoding.

use super::OxideDocument;
use crate::pdf::error::{PdfError, Result};
use crate::pdf::images::PdfImage;
use bytes::Bytes;

/// Extract all images from all pages of a PDF document using pdf_oxide.
///
/// # Arguments
///
/// * `doc` - Mutable reference to the oxide document
///
/// # Returns
///
/// A `Vec<PdfImage>` containing all successfully extracted images.
pub(crate) fn extract_images(doc: &mut OxideDocument) -> Result<Vec<PdfImage>> {
    let page_count = doc
        .doc
        .page_count()
        .map_err(|e| PdfError::MetadataExtractionFailed(format!("pdf_oxide: failed to get page count: {e}")))?;

    let mut all_images = Vec::new();

    for page_idx in 0..page_count {
        let page_images = extract_images_from_page(doc, page_idx)?;
        all_images.extend(page_images);
    }

    Ok(all_images)
}

/// Extract images from a single page of a PDF document using pdf_oxide.
///
/// # Arguments
///
/// * `doc` - Mutable reference to the oxide document
/// * `page_index` - Zero-based page index
///
/// # Returns
///
/// A `Vec<PdfImage>` containing all images found on the page.
pub(crate) fn extract_images_from_page(doc: &mut OxideDocument, page_index: usize) -> Result<Vec<PdfImage>> {
    let oxide_images = match doc.doc.extract_images(page_index) {
        Ok(images) => images,
        Err(e) => {
            tracing::debug!(page = page_index, "pdf_oxide: failed to extract images: {e}");
            return Ok(Vec::new());
        }
    };

    let page_number = page_index + 1; // Kreuzberg uses 1-indexed page numbers
    let mut images = Vec::with_capacity(oxide_images.len());

    for (img_index, oxide_img) in oxide_images.into_iter().enumerate() {
        match convert_oxide_image(oxide_img, page_number, img_index + 1) {
            Ok(img) => images.push(img),
            Err(e) => {
                tracing::debug!(
                    page = page_index,
                    image = img_index,
                    "pdf_oxide: failed to convert image: {e}"
                );
            }
        }
    }

    Ok(images)
}

/// Convert a pdf_oxide `PdfImage` to Kreuzberg's `PdfImage` type.
///
/// Handles JPEG passthrough (raw bytes are already valid JPEG) and
/// converts Raw pixel data to PNG using pdf_oxide's `to_png_bytes()`.
fn convert_oxide_image(
    oxide_img: pdf_oxide::extractors::PdfImage,
    page_number: usize,
    image_index: usize,
) -> Result<PdfImage> {
    let width = oxide_img.width() as i64;
    let height = oxide_img.height() as i64;
    let color_space = Some(format!("{:?}", oxide_img.color_space()));
    let bits_per_component = Some(oxide_img.bits_per_component() as i64);

    let (data, decoded_format, filters) = match oxide_img.data() {
        pdf_oxide::extractors::ImageData::Jpeg(jpeg_bytes) => {
            // JPEG passthrough: raw bytes are already a valid JPEG bitstream
            (
                Bytes::from(jpeg_bytes.clone()),
                "jpeg".to_string(),
                vec!["DCTDecode".to_string()],
            )
        }
        pdf_oxide::extractors::ImageData::Raw { .. } => {
            // Raw pixel data: encode as PNG via pdf_oxide's built-in encoder
            match oxide_img.to_png_bytes() {
                Ok(png_bytes) => (
                    Bytes::from(png_bytes),
                    "png".to_string(),
                    vec!["FlateDecode".to_string()],
                ),
                Err(e) => {
                    return Err(PdfError::MetadataExtractionFailed(format!(
                        "Failed to encode image as PNG: {e}"
                    )));
                }
            }
        }
    };

    Ok(PdfImage {
        page_number,
        image_index,
        width,
        height,
        color_space,
        bits_per_component,
        filters,
        data,
        decoded_format,
    })
}

/// Extract image positions from all pages for interleaving into the assembly pipeline.
///
/// Calls `doc.doc.extract_images(page_idx)` for each page and creates an
/// `(page_number, image_index)` pair for each image found. These positions
/// are used by the assembly pipeline to insert image placeholders into the
/// document structure.
///
/// # Arguments
///
/// * `doc` - Mutable reference to the oxide document
///
/// # Returns
///
/// A `Vec<(usize, usize)>` of (1-indexed page number, global image index) pairs.
pub(crate) fn extract_image_positions(doc: &mut OxideDocument) -> Result<Vec<(usize, usize)>> {
    let page_count = doc
        .doc
        .page_count()
        .map_err(|e| PdfError::MetadataExtractionFailed(format!("pdf_oxide: failed to get page count: {e}")))?;

    let mut positions = Vec::new();
    let mut global_index = 0usize;

    for page_idx in 0..page_count {
        let oxide_images = match doc.doc.extract_images(page_idx) {
            Ok(images) => images,
            Err(e) => {
                tracing::debug!(
                    page = page_idx,
                    "pdf_oxide: failed to extract images for positions: {e}"
                );
                continue;
            }
        };

        let page_number = page_idx + 1; // Kreuzberg uses 1-indexed page numbers

        for _img in &oxide_images {
            positions.push((page_number, global_index));
            global_index += 1;
        }
    }

    Ok(positions)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_convert_oxide_image_jpeg() {
        // JPEG magic bytes + minimal data
        let jpeg_data = vec![0xFF, 0xD8, 0xFF, 0xE0, 0x00, 0x10];
        let oxide_img = pdf_oxide::extractors::PdfImage::new(
            10,
            10,
            pdf_oxide::extractors::ColorSpace::DeviceRGB,
            8,
            pdf_oxide::extractors::ImageData::Jpeg(jpeg_data.clone()),
        );

        let result = convert_oxide_image(oxide_img, 1, 1).unwrap();
        assert_eq!(result.page_number, 1);
        assert_eq!(result.image_index, 1);
        assert_eq!(result.width, 10);
        assert_eq!(result.height, 10);
        assert_eq!(result.decoded_format, "jpeg");
        assert_eq!(result.filters, vec!["DCTDecode".to_string()]);
        assert_eq!(result.data.as_ref(), &jpeg_data[..]);
    }

    #[test]
    fn test_convert_oxide_image_raw_to_png() {
        // Create a minimal 2x2 RGB raw image
        let pixels = vec![
            255, 0, 0, // red
            0, 255, 0, // green
            0, 0, 255, // blue
            255, 255, 0, // yellow
        ];
        let oxide_img = pdf_oxide::extractors::PdfImage::new(
            2,
            2,
            pdf_oxide::extractors::ColorSpace::DeviceRGB,
            8,
            pdf_oxide::extractors::ImageData::Raw {
                pixels,
                format: pdf_oxide::extractors::PixelFormat::RGB,
            },
        );

        let result = convert_oxide_image(oxide_img, 1, 1).unwrap();
        assert_eq!(result.decoded_format, "png");
        // PNG magic bytes check
        assert!(result.data.starts_with(b"\x89PNG\r\n\x1a\n"));
    }
}
