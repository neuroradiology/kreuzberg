```ruby title="Ruby"
require 'xberg'

items = [
  Xberg::ExtractInput.new(
    content: File.read('doc1.pdf'),
    mime_type: 'application/pdf'
  ),
  Xberg::ExtractInput.new(
    content: File.read('doc2.docx'),
    mime_type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
  ),
  Xberg::ExtractInput.new(
    content: File.read('doc3.xlsx'),
    mime_type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
  )
]

config = Xberg::ExtractionConfig.new(use_cache: true)

results = Xberg.extract_batch_sync(items, config: config)

results.each { |result| puts "Extracted: #{result.content.length} chars" }
```
