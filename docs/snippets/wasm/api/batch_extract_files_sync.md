```typescript title="WASM"
// WASM has no batch helper; await extract for each file (in parallel via Promise.all).
import init, { extract } from "xberg-wasm";

await init();

const input = document.getElementById("files") as HTMLInputElement;
const files = Array.from(input.files ?? []);

const results = await Promise.all(
  files.map(async (file) => {
    const bytes = new Uint8Array(await file.arrayBuffer());
    return extract(bytes, file.type || "application/pdf", undefined);
  }),
);

results.forEach((result, i) => {
  console.log(`File ${i + 1}: ${result.content.length} characters`);
});
```
