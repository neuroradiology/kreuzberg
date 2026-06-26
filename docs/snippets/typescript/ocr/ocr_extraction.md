```typescript title="TypeScript"
import { extractSync } from "@xberg/node";

const config = {
  ocr: {
    backend: "tesseract",
    language: "eng",
  },
};

const result = extractSync("scanned.pdf", null, config);
console.log(result.content);
```
