```typescript title="WASM"
import init, { extract } from "xberg-wasm";

await init();

const result = await extract("document.pdf", undefined, undefined);
console.log(`Extracted content: ${result.content}`);
console.log(`Tables found: ${result.tables?.length ?? 0}`);
console.log(`Format: ${result.metadata?.format ?? "unknown"}`);
```
