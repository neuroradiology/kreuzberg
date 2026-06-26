```typescript title="TypeScript"
import { extractSync } from "@xberg/node";

const config = {
  ocr: {
    backend: "tesseract",
    language: "eng+deu+fra",
  },
};

const result = extractSync("multilingual.pdf", null, config);
console.log(result.content);
```
