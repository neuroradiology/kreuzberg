```ruby title="Ruby"
require 'kreuzberg'

# Using keyword arguments with defaults
config = Kreuzberg::Config::Extraction.new(
  pdf_options: Kreuzberg::Config::PDF.new(
    extract_images: true,
    hierarchy: Kreuzberg::Config::Hierarchy.new(
      enabled: true,
      k_clusters: 6,
      include_bbox: true,
      ocr_coverage_threshold: 0.8
    )
  )
)

# Using hash syntax alternative
config = Kreuzberg::Config::Extraction.new(
  pdf_options: Kreuzberg::Config::PDF.new(
    extract_images: true,
    hierarchy: {
      enabled: true,
      k_clusters: 6,
      include_bbox: true,
      ocr_coverage_threshold: 0.8
    }
  )
)
```
