```typescript title="TypeScript"
import { extractSync } from "xberg";
import { readFileSync } from "fs";

const content = readFileSync("document.pdf");
const result = extractSync(content, "application/pdf");

console.log(result.content);
console.log(`Tables: ${result.tables?.length ?? 0}`);
```
