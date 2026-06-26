```python title="Python"
from xberg import (
    extract_batch_sync,
    ExtractInput,
    ExtractionConfig,
    XbergError,
)

items = [
    ExtractInput(path="doc1.pdf"),
    ExtractInput(path="doc2.docx"),
    ExtractInput(path="missing.html"),
]

config = ExtractionConfig()

try:
    results = extract_batch_sync(items, config=config)
    for i, result in enumerate(results):
        if result.metadata.error:
            print(f"Document {i}: ERROR - {result.metadata.error}")
        else:
            print(f"Document {i}: {len(result.content)} chars, {len(result.tables)} tables")
except XbergError as e:
    print(f"Batch extraction failed: {e}")
    raise
```
