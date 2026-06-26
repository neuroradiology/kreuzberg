```typescript title="WASM"
import { extract, initWasm } from "@xberg-io/xberg-wasm";

await initWasm();

const response = await fetch("document.pdf");
const buffer = await response.arrayBuffer();
const data = new Uint8Array(buffer);

const result = await extract(data, "application/pdf");
console.log(result.content);
```
