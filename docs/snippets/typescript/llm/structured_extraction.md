```typescript title="TypeScript"
import { extractSync } from "@xberg/node";

const config = {
  structuredExtraction: {
    schema: {
      type: "object",
      properties: {
        title: { type: "string" },
        authors: { type: "array", items: { type: "string" } },
        date: { type: "string" },
      },
      required: ["title", "authors", "date"],
      additionalProperties: false,
    },
    llm: {
      model: "openai/gpt-4o-mini",
    },
    strict: true,
  },
};

const result = extractSync("paper.pdf", null, config);
console.log(result.structuredOutput);
```
