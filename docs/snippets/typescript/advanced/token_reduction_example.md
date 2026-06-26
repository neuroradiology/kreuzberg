```typescript title="TypeScript"
import { extract } from "@xberg/node";

const config = {
  tokenReduction: {
    level: "Moderate",
    preserveMarkdown: true,
  },
};

const result = await extract("verbose_document.pdf", null, config);

console.log(`Reduced content length: ${result.content?.length ?? 0} chars`);
```
