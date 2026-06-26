```typescript title="TypeScript"
import { extractSync } from "@xberg/node";

const config = {
  forceOcr: true,
  ocr: {
    backend: "vlm",
    vlmConfig: {
      model: "openai/gpt-4o-mini",
    },
  },
};

const result = extractSync("scan.pdf", null, config);
console.log(result.content);
```
