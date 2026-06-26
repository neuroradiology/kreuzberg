```kotlin title="Kotlin"
import io.xberg.*
import java.nio.file.Paths

fun main() {
    val config = ExtractionConfig.builder().build()
    val items = listOf(
        ExtractInput(Paths.get("doc1.pdf"), null),
        ExtractInput(Paths.get("doc2.docx"), null),
        ExtractInput(Paths.get("report.pdf"), null),
    )
    val results = Xberg.extractBatchSync(items, config)

    results.forEachIndexed { index, result ->
        println("File $index: ${result.content().length} chars")
    }
}
```
