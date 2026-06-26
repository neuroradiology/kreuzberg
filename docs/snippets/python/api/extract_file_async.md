```python title="Python"
import asyncio
from xberg import extract, ExtractionConfig

async def main() -> None:
    result = await extract("document.pdf", config=ExtractionConfig())
    print(result.content[:200])
    print(f"Tables: {len(result.tables)}")
    print(f"Format: {result.metadata.format_type}")

asyncio.run(main())
```
