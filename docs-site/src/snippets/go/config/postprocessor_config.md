```go title="Go"
package main

import "github.com/xberg-io/xberg/packages/go"

func main() {
	enabled := true
	cfg := &xberg.ExtractionConfig{
		Postprocessor: &xberg.PostProcessorConfig{
			Enabled:            &enabled,
			EnabledProcessors:  []string{"deduplication", "whitespace_normalization"},
			DisabledProcessors: []string{"mojibake_fix"},
		},
	}

	_ = cfg
}
```
