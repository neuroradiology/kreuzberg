```typescript title="TypeScript"
import { extractBatchSync } from "xberg";

const items = [
  { path: "doc1.pdf", config: undefined },
  { path: "doc2.docx", config: undefined },
  { path: "report.pdf", config: undefined },
];

const results = extractBatchSync(items);

results.forEach((result, i) => {
  console.log(`File ${i}: ${result.content.length} chars`);
});
```
