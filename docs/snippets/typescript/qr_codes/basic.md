```typescript title="TypeScript"
import { extract } from '@xberg/node';

const result = await extract("ticket.pdf", { qrCodes: true });
for (const image of result.images ?? []) {
    for (const qr of image.qrCodes ?? []) {
        console.log(qr.payload);
    }
}
```
