```typescript title="TypeScript"
import { extractSync } from "@xberg/node";
import { readFileSync } from "fs";

const data = readFileSync("document.pdf");
const result = extractSync(data, "application/pdf");
console.log(result.content);
```
