```typescript title="WASM"
// WASM exposes only async extraction. Read the file as bytes and call extract.
import init, { extract } from "xberg-wasm";

await init();

const fileInput = document.getElementById("file") as HTMLInputElement;
const file = fileInput.files?.[0];
if (file) {
  const bytes = new Uint8Array(await file.arrayBuffer());
  const result = await extract(bytes, file.type || "application/pdf", undefined);
  console.log(result.content);
  console.log(`Tables: ${result.tables?.length ?? 0}`);
}
```
