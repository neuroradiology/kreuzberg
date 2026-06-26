```typescript title="TypeScript"
import { extract } from "@xberg-io/xberg";
import { readFile } from "fs/promises";

const data = await readFile("document.pdf");
const result = await extract(data, "application/pdf");
console.log(result.content);
```
