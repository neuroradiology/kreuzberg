```typescript title="TypeScript"
import { extractSync } from "xberg";

const result = extractSync("document.pdf");

console.log(result.content);
console.log(`MIME type: ${result.mime_type}`);
console.log(`Tables: ${result.tables?.length ?? 0}`);
```
