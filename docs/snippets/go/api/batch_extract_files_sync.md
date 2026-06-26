```go title="Go"
package main

import (
	"log"

	"github.com/xberg-io/xberg"
)

func main() {
	items := []xberg.ExtractInput{
		{Path: "doc1.pdf"},
		{Path: "doc2.docx"},
		{Path: "doc3.pptx"},
	}

	results, err := xberg.ExtractBatchSync(items, xberg.ExtractionConfig{})
	if err != nil {
		log.Fatalf("batch extraction failed: %v", err)
	}

	for i, result := range results {
		println("Doc", i, "content length:", len(result.Content))
	}
}
```
