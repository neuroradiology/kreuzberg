```typescript title="TypeScript"
import { extract } from '@xberg/node';

const result = await extract("packet.pdf", {
    pageClassification: {
        labels: ["invoice", "contract", "id_document", "receipt"],
        llm: { model: "openai/gpt-4o-mini" },
    },
});

for (const page of result.pageClassifications ?? []) {
    console.log(`page ${page.pageNumber}: ${page.labels[0]?.label}`);
}
```
