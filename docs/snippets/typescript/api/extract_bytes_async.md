```typescript title="TypeScript"
import { extract } from "xberg";
import { readFileSync } from "fs";

async function main() {
  const content = readFileSync("document.pdf");
  const result = await extract(content, "application/pdf");

  console.log(result.content);
  console.log(`Tables: ${result.tables?.length ?? 0}`);
}

main();
```
