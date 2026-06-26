```python title="Python"
from xberg import extract_batch_sync, ExtractInput, ExtractionConfig

items = [
    ExtractInput(content=b"PDF content", mime_type="application/pdf"),
    ExtractInput(content=b"<html>...</html>", mime_type="text/html"),
]

results = extract_batch_sync(items, ExtractionConfig())

for i, result in enumerate(results):
    print(f"Item {i}: {len(result.content)} chars extracted")
```
