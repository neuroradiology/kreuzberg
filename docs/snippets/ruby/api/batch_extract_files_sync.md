```ruby title="Ruby"
require 'xberg'

items = [
  Xberg::ExtractInput.new(path: 'doc1.pdf'),
  Xberg::ExtractInput.new(path: 'doc2.docx'),
  Xberg::ExtractInput.new(path: 'doc3.pptx')
]

config = Xberg::ExtractionConfig.new(use_cache: true)

results = Xberg.extract_batch_sync(items, config: config)

results.each_with_index do |result, idx|
  puts "Document #{idx + 1}:"
  puts "  Extracted: #{result.content.length} characters"
  puts "  Quality: #{result.quality_score}"
  puts "  MIME: #{result.mime_type}"
end
```
