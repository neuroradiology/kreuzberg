```typescript title="TypeScript"
import { extract } from "@xberg-io/xberg";

const result = await extract("document.pdf");

console.log(result.content);
console.log(`Tables: ${result.tables.length}`);
console.log(`Metadata: ${JSON.stringify(result.metadata)}`);
```
