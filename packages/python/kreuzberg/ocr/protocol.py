"""Protocol for Python OCR backends compatible with Rust FFI bridge."""

from __future__ import annotations

from typing import Any, Protocol


class OcrBackendProtocol(Protocol):
    """Protocol for OCR backends registered with the Rust extraction core.

    Required Methods:
        name: Return backend name (e.g., 'easyocr')
        supported_languages: Return list of supported language codes
        process_image: Process image bytes and return extraction result

    Optional Methods:
        initialize, shutdown, version, process_document, supports_document_processing
    """

    def name(self) -> str:
        """Return the backend identifier name."""
        ...

    def supported_languages(self) -> list[str]:
        """Return list of supported ISO 639 language codes."""
        ...

    def process_image(self, image_bytes: bytes, language: str) -> dict[str, Any]:
        """Process raw image bytes and return extraction result dict."""
        ...

    def process_image_file(self, path: str, language: str) -> dict[str, Any]:
        """Process an image file at the given path and return extraction result dict."""
        ...

    def supports_document_processing(self) -> bool:
        """Return True if the backend supports whole-document processing."""
        ...

    def process_document(self, path: str, language: str) -> dict[str, Any]:
        """Process a document file at the given path and return extraction result dict."""
        ...

    def initialize(self) -> None:
        """Initialize the backend and load any required models."""
        ...

    def shutdown(self) -> None:
        """Release backend resources."""
        ...
