```typescript title="WASM"
import { extract, initWasm } from "@xberg/wasm";

await initWasm();

const fileInputs = document.getElementById("files") as HTMLInputElement;
const files = Array.from(fileInputs.files || []);

const results = await Promise.all(files.map((file) => extract(file)));

results.forEach((result, i) => {
  console.log(`File ${i + 1}: ${result.content.length} characters`);
});
```
