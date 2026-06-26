```go title="Go"
package main

import (
	"log"

	"github.com/xberg-io/xberg"
)

func main() {
	result, err := xberg.ExtractSync("document.pdf", nil, xberg.ExtractionConfig{})
	if err != nil {
		log.Fatalf("extraction failed: %v", err)
	}

	println("Content:", result.Content)
}
```
