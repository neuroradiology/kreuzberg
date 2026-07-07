```go title="Go"
package main

import (
	"github.com/xberg-io/xberg/packages/go"
)

func main() {
	enableQualityProcessing := true

	config := &xberg.ExtractionConfig{
		EnableQualityProcessing: &enableQualityProcessing,
	}
	_ = config
}
```
