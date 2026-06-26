```kotlin title="Kotlin"
import io.xberg.*

fun main() {
    val config = ExtractionConfig.builder().build()
    val items = listOf(
        ExtractInput("Hello, world!".toByteArray(), "text/plain", null),
        ExtractInput("# Heading\n\nParagraph text.".toByteArray(), "text/markdown", null),
    )
    val results = Xberg.extractBatchSync(items, config)

    results.forEachIndexed { index, result ->
        println("Item $index: ${result.content().length} chars")
    }
}
```
