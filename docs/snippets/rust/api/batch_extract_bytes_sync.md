```rust title="Rust"
use xberg::{extract_batch_sync, ExtractInput, ExtractionConfig};

fn main() -> xberg::Result<()> {
    let config = ExtractionConfig::default();
    let items = vec![
        ExtractInput {
            content: b"Hello, world!".to_vec(),
            mime_type: "text/plain".to_string(),
            config: None,
        },
        ExtractInput {
            content: b"# Heading\n\nParagraph text.".to_vec(),
            mime_type: "text/markdown".to_string(),
            config: None,
        },
    ];
    let results = extract_batch_sync(items, &config)?;

    for (i, result) in results.iter().enumerate() {
        println!("Item {}: {} chars", i, result.content.len());
    }
    Ok(())
}
```
