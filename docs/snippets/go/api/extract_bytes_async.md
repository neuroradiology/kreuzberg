```go title="Go"
package main

import (
	"log"
	"os"

	"github.com/xberg-io/xberg"
)

func main() {
	content, err := os.ReadFile("document.pdf")
	if err != nil {
		log.Fatalf("failed to read file: %v", err)
	}

	result, err := xberg.Extract(content, "application/pdf", xberg.ExtractionConfig{})
	if err != nil {
		log.Fatalf("extraction failed: %v", err)
	}

	println("Content:", result.Content)
}
```
