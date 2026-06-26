```go title="Go"
package main

import (
	"log"
	"os"

	"github.com/xberg-io/xberg"
)

func main() {
	doc1, _ := os.ReadFile("doc1.pdf")
	doc2, _ := os.ReadFile("doc2.docx")

	items := []xberg.ExtractInput{
		{Content: doc1, MimeType: "application/pdf"},
		{Content: doc2, MimeType: "application/vnd.openxmlformats-officedocument.wordprocessingml.document"},
	}

	results, err := xberg.ExtractBatchSync(items, xberg.ExtractionConfig{})
	if err != nil {
		log.Fatalf("batch extraction failed: %v", err)
	}

	println("Processed", len(results), "documents")
}
```
