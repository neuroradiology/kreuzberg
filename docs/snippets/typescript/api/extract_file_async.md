```typescript title="TypeScript"
import { extract } from "xberg";

async function main() {
  const result = await extract("document.pdf");

  console.log(result.content);
  console.log(`Tables: ${result.tables?.length ?? 0}`);
}

main();
```
