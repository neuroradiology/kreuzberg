```rust title="Rust"
use xberg::extract_sync;

fn main() -> xberg::Result<()> {
    let result = extract_sync("document.pdf", None, &Default::default())?;

    println!("Extracted content: {}", result.content);
    println!("Tables found: {}", result.tables.len());
    println!("Format: {:?}", result.metadata.as_ref().and_then(|m| m.format.as_ref()));
    Ok(())
}
```
