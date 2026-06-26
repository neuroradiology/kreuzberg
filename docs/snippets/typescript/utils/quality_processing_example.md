```typescript title="TypeScript"
import { extract } from "@xberg/node";

const config = {
  enableQualityProcessing: true,
};

const result = await extract("scanned_document.pdf", null, config);
console.log(`Content length: ${result.content.length} characters`);
console.log(`Metadata: ${JSON.stringify(result.metadata)}`);
```
