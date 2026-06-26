# Extraction Basics

Two extraction functions are the public entry points:

| Function          | Input model      | Purpose                                      |
| ----------------- | ---------------- | -------------------------------------------- |
| `extract`         | `ExtractInput`   | Extract one file path or in-memory byte blob |
| `extract_batch`   | `ExtractInput[]` | Extract mixed file and byte inputs together  |

`ExtractInput` carries the input kind and source-specific metadata. File inputs use a path and can optionally override MIME type. Byte inputs carry the bytes and must include a MIME type because there is no file extension to infer from.

## Extract One Input

=== "Python"

    ```python title="extract_one.py"
    from xberg import ExtractInput, extract

    result = await extract(ExtractInput.file("document.pdf"))
    print(result.content)
    ```

=== "TypeScript"

    ```typescript title="extract-one.ts"
    import { ExtractInput, extract } from "@xberg-io/xberg";

    const result = await extract(ExtractInput.file("document.pdf"));
    console.log(result.content);
    ```

=== "Rust"

    ```rust title="extract_one.rs"
    use xberg::{extract, ExtractInput, ExtractionConfig};

    let config = ExtractionConfig::default();
    let result = extract(ExtractInput::file("document.pdf"), &config).await?;
    println!("{}", result.content);
    ```

## Extract from Bytes

When the file is already loaded in memory, pass bytes through `ExtractInput` with an explicit MIME type.

=== "Python"

    ```python title="extract_from_bytes.py"
    from xberg import ExtractInput, extract

    with open("document.pdf", "rb") as file:
        data = file.read()

    result = await extract(ExtractInput.bytes(data, mime_type="application/pdf"))
    ```

=== "TypeScript"

    ```typescript title="extract-bytes.ts"
    import { readFile } from "node:fs/promises";
    import { ExtractInput, extract } from "@xberg-io/xberg";

    const data = await readFile("document.pdf");
    const result = await extract(ExtractInput.bytes(data, "application/pdf"));
    ```

=== "Rust"

    ```rust title="extract_from_bytes.rs"
    use xberg::{extract, ExtractInput, ExtractionConfig};

    let data = std::fs::read("document.pdf")?;
    let config = ExtractionConfig::default();
    let result = extract(ExtractInput::bytes(data, "application/pdf"), &config).await?;
    ```

## Batch Processing

`extract_batch` accepts a list of `ExtractInput` values. Mix file and byte inputs in the same request when a pipeline receives documents from multiple sources.

=== "Python"

    ```python title="extract_batch.py"
    from xberg import ExtractInput, extract_batch

    inputs = [
        ExtractInput.file("report.pdf"),
        ExtractInput.file("scan.tiff", mime_type="image/tiff"),
    ]

    results = await extract_batch(inputs)
    ```

=== "TypeScript"

    ```typescript title="extract-batch.ts"
    import { ExtractInput, extractBatch } from "@xberg-io/xberg";

    const results = await extractBatch([
      ExtractInput.file("report.pdf"),
      ExtractInput.file("scan.tiff", { mimeType: "image/tiff" }),
    ]);
    ```

=== "Rust"

    ```rust title="extract_batch.rs"
    use xberg::{extract_batch, ExtractInput, ExtractionConfig};

    let config = ExtractionConfig::default();
    let inputs = vec![
        ExtractInput::file("report.pdf"),
        ExtractInput::file("scan.tiff").with_mime_type("image/tiff"),
    ];

    let results = extract_batch(inputs, &config).await?;
    ```

### Per-Input Configuration

When a batch contains a mix of document types that need different settings, attach per-input overrides to `ExtractInput` while sharing a common batch config.

=== "Python"

    ```python title="mixed_batch.py"
    from xberg import (
        ExtractionConfig,
        ExtractInput,
        OcrConfig,
        extract_batch,
    )

    config = ExtractionConfig(output_format="markdown")

    inputs = [
        ExtractInput.file("report.pdf"),
        ExtractInput.file(
            "scan.tiff",
            force_ocr=True,
            ocr=OcrConfig(backend="tesseract", language="deu"),
        ),
        ExtractInput.file("notes.html", output_format="plain"),
    ]

    results = await extract_batch(inputs, config)
    ```

=== "TypeScript"

    ```typescript title="mixed_batch.ts"
    import { ExtractInput, extractBatch } from "@xberg-io/xberg";

    const results = await extractBatch(
      [
        ExtractInput.file("report.pdf"),
        ExtractInput.file("scan.tiff", {
          forceOcr: true,
          ocr: { backend: "tesseract", language: "deu" },
        }),
        ExtractInput.file("notes.html", { outputFormat: "plain" }),
      ],
      { outputFormat: 'markdown' },
    );
    ```

=== "Rust"

    ```rust title="mixed_batch.rs"
    use xberg::{
        extract_batch, ExtractInput, ExtractionConfig, FileExtractionConfig,
        OcrConfig, OutputFormat,
    };

    let config = ExtractionConfig {
        output_format: OutputFormat::Markdown,
        ..Default::default()
    };

    let inputs = vec![
        ExtractInput::file("report.pdf"),
        ExtractInput::file("scan.tiff").with_config(FileExtractionConfig {
            force_ocr: Some(true),
            ocr: Some(OcrConfig {
                backend: "tesseract".to_string(),
                language: "deu".to_string(),
                ..Default::default()
            }),
            ..Default::default()
        }),
        ExtractInput::file("notes.html").with_config(FileExtractionConfig {
            output_format: Some(OutputFormat::Plain),
            ..Default::default()
        }),
    ];

    let results = extract_batch(inputs, &config).await?;
    ```

Fields set to `None` in `FileExtractionConfig` inherit the batch default. Batch-level concerns like `max_concurrent_extractions`, `use_cache`, and `security_limits` cannot be overridden per input. See the [Configuration Reference](../reference/configuration.md#fileextractionconfig) for the full list of overridable fields.

## Content Filtering

Xberg strips running headers, footers, watermarks, and cross-page repeating text by default so that downstream RAG and LLM pipelines see clean body content. `ContentFilterConfig` lets you opt back in to any of these when you need them, for example when extracting legal forms where the header carries the case number, or when running text analysis on a PDF whose brand name was being incorrectly removed by the repeating-text heuristic.

By default headers, footers, and watermarks are stripped and cross-page repeating text is deduplicated; see [ContentFilterConfig](../reference/configuration.md#contentfilterconfig) for field-level defaults and per-format behavior.

=== "Python"

    ```python title="keep_headers_footers.py"
    from xberg import (
        ContentFilterConfig,
        ExtractionConfig,
        ExtractInput,
        extract,
    )

    # Legal/forms work: keep header and footer text
    config = ExtractionConfig(
        content_filter=ContentFilterConfig(
            include_headers=True,
            include_footers=True,
        ),
    )

    result = await extract(ExtractInput.file("contract.pdf"), config=config)
    ```

=== "TypeScript"

    ```typescript title="disable_repeating_text.ts"
    import { extract } from "@xberg-io/xberg";

    // Disable cross-page deduplication so brand names aren't stripped
    const result = await extract("brochure.pdf", {
      contentFilter: {
        stripRepeatingText: false,
      },
    });
    ```

=== "Rust"

    ```rust title="content_filter.rs"
    use xberg::{extract, ContentFilterConfig, ExtractInput, ExtractionConfig};

    let config = ExtractionConfig {
        content_filter: Some(ContentFilterConfig {
            include_headers: true,
            include_footers: true,
            strip_repeating_text: true,
            include_watermarks: false,
        }),
        ..Default::default()
    };

    let result = extract(ExtractInput::file("contract.pdf"), &config).await?;
    ```

When a layout-detection model is active, it can independently classify regions as page headers or footers and strip them per page. Setting `include_headers=True` / `include_footers=True` also disables that per-page stripping. See the [reference page](../reference/configuration.md#contentfilterconfig) for the full field semantics and per-format behavior.

## Supported Formats

Xberg supports 96 file formats across 8 categories:

| Category          | Extensions                                               | Notes                               |
| ----------------- | -------------------------------------------------------- | ----------------------------------- |
| **PDF**           | `.pdf`                                                   | Native text + OCR for scanned pages |
| **Images**        | `.png`, `.jpg`, `.jpeg`, `.tiff`, `.bmp`, `.webp`, `.heic`, `.heif`, `.avif` | OCR backend; HEIC/HEIF/AVIF need `heic` feature + libheif |
| **Office**        | `.docx`, `.pptx`, `.xlsx`                                | Modern formats via native parsers   |
| **Legacy Office** | `.doc`, `.ppt`                                           | Native OLE/CFB parsing              |
| **Email**         | `.eml`, `.msg`                                           | Full support including attachments  |
| **Web**           | `.html`, `.htm`                                          | Converted to Markdown with metadata |
| **Text**          | `.md`, `.txt`, `.xml`, `.json`, `.yaml`, `.toml`, `.csv` | Direct extraction                   |
| **Archives**      | `.zip`, `.tar`, `.tar.gz`, `.tar.bz2`                    | Recursive extraction                |

### Image metadata and EXIF

For every supported image format — JPEG, PNG, TIFF, WebP, BMP, GIF, JPEG 2000,
HEIC, HEIF, AVIF — Xberg returns an `ImageMetadata` block on
`metadata.format` containing:

- **`width`** / **`height`** in pixels
- **`format`** — uppercase format tag (e.g. `JPEG`, `PNG`, `HEIF`)
- **`exif`** — a key/value map of EXIF tags

EXIF extraction is powered by the pure-Rust `nom-exif` integration and covers
camera identity (Make, Model, LensModel, LensSpecification, Software),
timestamps (DateTimeOriginal, CreateDate, OffsetTime, SubSecTime), full
exposure parameters (ExposureTime, FNumber, ISO, ApertureValue,
ShutterSpeedValue, ExposureProgram, ExposureMode, MeteringMode, Flash,
SceneCaptureType), the complete GPS block (GPSLatitude, GPSLongitude,
GPSAltitude, GPSTimeStamp, GPSDateStamp, GPSSpeed, GPSImgDirection,
GPSMapDatum, GPSProcessingMethod), color space, thumbnail offsets, and
provenance fields (Copyright, ImageDescription, ImageUniqueID).

EXIF works on every target, including `wasm-target` and `android-target`,
because `nom-exif` is pure Rust. HEIC / HEIF / AVIF pixel decoding requires
the `heic` Cargo feature and the system `libheif` library, and is therefore
**native-only** — see the [installation guide](../getting-started/installation.md#heif--heic--avif-support).

When the `heic` feature is enabled, HEIC / HEIF / AVIF inputs are decoded to
RGBA via `libheif`, re-encoded as PNG, and then flow through the standard
OCR / layout pipeline. EXIF is read from the original HEIC bytes before the
PNG re-encode so no metadata is lost.

## Page Tracking

Xberg can track page boundaries and extract per-page content. Page tracking availability depends on the format:

- **PDF** — Full byte-accurate page tracking with O(1) lookup
- **PPTX** — Slide boundary tracking (each slide = one page)
- **DOCX** — Best-effort detection using explicit `<w:br type="page"/>` tags
- **Other formats** — No page tracking

Enable page extraction with `PageConfig`:

```python title="page_tracking.py"
config = ExtractionConfig(
    pages=PageConfig(
        insert_page_markers=True,
        marker_format="\n\n<!-- PAGE {page_num} -->\n\n"
    )
)
```

Page markers like `<!-- PAGE 1 -->` are inserted at boundaries in the `content` field — useful for LLMs that need to understand document layout. When both page tracking and chunking are enabled, chunks automatically include `first_page` and `last_page` metadata.

See [PageConfig Reference](../reference/configuration.md#pageconfig) for all options and [Advanced Page Tracking](./advanced.md) for chunk-to-page mapping examples.

## Code File Extraction

Source code files (`.py`, `.rs`, `.ts`, `.go`, etc.) go through tree-sitter and produce a `ProcessResult` on `ExtractionResult.code_intelligence` (structure, imports/exports, symbols, docstrings, diagnostics, semantic chunks). Code files bypass text chunking — TSLP's function/class-aware `CodeChunks` map directly to Xberg `Chunk`s with semantic `chunk_type` and heading context.

See [Code Intelligence](code-intelligence.md) for usage and [`TreeSitterProcessConfig`](../reference/configuration.md#treesitterprocessconfig) for fields.

## PDF Page Rendering

Render individual PDF pages as PNG images. Unlike the extraction pipeline (which parses text, tables, metadata), this API produces raw pixel data for thumbnails, vision model input, or custom OCR pipelines.

### Two Approaches

| API               | When to use                                                            |
| ----------------- | ---------------------------------------------------------------------- |
| `render_pdf_page` | You know which page you need, or only need a few pages                 |
| `PdfPageIterator` | Process every page sequentially without loading all images into memory |

### DPI Configuration

| DPI           | Pixel size (US Letter) | Use case                        |
| ------------- | ---------------------- | ------------------------------- |
| 72            | 612 x 792              | Thumbnails, quick previews      |
| 150 (default) | 1275 x 1650            | General-purpose, screen display |
| 300           | 2550 x 3300            | OCR input, print quality        |

**Tip:** Use 300 DPI when rendering pages for OCR or vision models. The default 150 DPI may reduce recognition accuracy on small text.

## MIME Type Detection

When extracting from bytes, `ExtractInput` requires an explicit MIME type since there's no file extension to infer it from. For file paths, auto-detection from the extension is automatic.

### Example: Override MIME Type

```python title="Python"
from xberg import ExtractInput, extract

# File without extension — provide MIME type explicitly
result = await extract(
    ExtractInput.file("document_copy", mime_type="application/pdf"),
    config=config,
)
```

## Error Handling

All extraction functions raise typed exceptions on failure. Catch specific exceptions to handle different failure modes:

=== "Python"

    --8<-- "snippets/python/utils/error_handling.md"

=== "TypeScript"

    --8<-- "snippets/typescript/api/error_handling.md"

=== "Rust"

    --8<-- "snippets/rust/api/error_handling.md"

=== "Go"

    --8<-- "snippets/go/api/error_handling.md"

=== "Java"

    --8<-- "snippets/java/api/error_handling.md"

=== "C#"

    --8<-- "snippets/csharp/error_handling.md"

=== "Ruby"

    --8<-- "snippets/ruby/api/error_handling.md"

=== "R"

    --8<-- "snippets/r/api/error_handling.md"

=== "C"

    --8<-- "snippets/c/api/error_handling.md"

=== "Wasm"

    --8<-- "snippets/wasm/api/error_handling_wasm.md"

!!! Warning "System Errors"
`OSError` (Python), `IOException` (Rust), and system-level errors always propagate through. These indicate real system problems (permissions, disk space, etc.) that your application should handle.

## Next Steps

- [Configuration](configuration.md) — all configuration options and file formats
- [OCR Guide](ocr.md) — set up optical character recognition
- [Advanced Features](advanced.md) — chunking, language detection, embeddings
- [Element-Based Output](output-formats.md#element-based-output) — structured element arrays for RAG
- [Document Structure](output-formats.md#document-structure) — hierarchical tree output
