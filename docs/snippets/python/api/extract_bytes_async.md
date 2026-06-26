```python title="Python"
import asyncio
from xberg import extract, ExtractionConfig

async def main() -> None:
    with open("document.pdf", "rb") as f:
        content = f.read()

    result = await extract(content, "application/pdf", config=ExtractionConfig())
    print(result.content[:200])
    print(f"Tables: {len(result.tables)}")

asyncio.run(main())
```
