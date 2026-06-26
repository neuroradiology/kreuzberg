```typescript title="WASM"
import { extract, initWasm } from "@xberg-io/xberg-wasm";

await initWasm();

const fileInput = document.getElementById("file") as HTMLInputElement;
const file = fileInput.files?.[0];

if (file) {
  const result = await extract(file);
  const content = result.content;
  const tableCount = result.tables.length;

  console.log(`Content length: ${content.length} characters`);
  console.log(`Tables: ${tableCount}`);
}
```
