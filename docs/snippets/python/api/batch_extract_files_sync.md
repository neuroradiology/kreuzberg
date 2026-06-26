```python title="Python"
from xberg import extract_batch_sync, ExtractInput, ExtractionConfig

items = [
    ExtractInput(path="doc1.pdf"),
    ExtractInput(path="doc2.docx"),
    ExtractInput(path="doc3.html"),
]

results = extract_batch_sync(items, ExtractionConfig())

for i, result in enumerate(results):
    print(f"Document {i}: {len(result.content)} chars, {len(result.tables)} tables")
```
