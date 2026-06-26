```python title="Python"
from xberg import extract_sync, ExtractionConfig

with open("document.pdf", "rb") as f:
    content = f.read()

result = extract_sync(content, "application/pdf", config=ExtractionConfig())

print(result.content[:200])
print(f"Tables: {len(result.tables)}")
```
