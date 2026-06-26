```typescript title="TypeScript"
import { extractSync } from "@xberg/node";

const config = {
  useCache: true,
  enableQualityProcessing: true,
};

const result = extractSync("document.pdf", null, config);

console.log(result.content);
console.log(`MIME Type: ${result.mimeType}`);
```
