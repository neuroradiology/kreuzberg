```typescript title="TypeScript"
import { extractSync } from "@xberg-io/xberg";
import { readFileSync } from "fs";

const data = readFileSync("document.pdf");
const result = extractSync(data, "application/pdf");
console.log(result.content);
```
