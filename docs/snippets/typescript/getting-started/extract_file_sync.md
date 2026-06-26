```typescript title="TypeScript"
import { extractSync } from "@xberg/node";

const result = extractSync("document.pdf");

console.log(result.content);
console.log(`Tables: ${result.tables.length}`);
console.log(`Metadata: ${JSON.stringify(result.metadata)}`);
```
