```rust title="Rust"
use xberg::{extract_batch_sync, ExtractInput, ExtractionConfig};

fn main() -> xberg::Result<()> {
    let config = ExtractionConfig::default();
    let items = vec![
        ExtractInput { path: "doc1.pdf".into(), config: None },
        ExtractInput { path: "doc2.docx".into(), config: None },
        ExtractInput { path: "report.pdf".into(), config: None },
    ];
    let results = extract_batch_sync(items, &config)?;

    for (i, result) in results.iter().enumerate() {
        println!("File {}: {} chars", i, result.content.len());
    }
    Ok(())
}
```
