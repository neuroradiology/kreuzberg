```typescript title="TypeScript"
import { extractBatchSync } from "@xberg-io/xberg";

const files = ["doc1.pdf", "doc2.docx", "doc3.pptx"];
const results = extractBatchSync(files);

results.forEach((result, i) => {
  console.log(`File ${i + 1}: ${result.content.length} characters`);
});
```
