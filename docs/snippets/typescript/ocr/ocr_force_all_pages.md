```typescript title="TypeScript"
import { extractSync } from "@xberg/node";

const config = {
  ocr: {
    backend: "tesseract",
  },
  forceOcr: true,
};

const result = extractSync("document.pdf", null, config);
console.log(result.content);
```
