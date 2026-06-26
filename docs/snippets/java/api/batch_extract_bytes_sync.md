```java title="Java"
import io.xberg.Xberg;
import io.xberg.ExtractionResult;
import io.xberg.ExtractInput;
import io.xberg.ExtractionConfig;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.util.List;
import java.util.Arrays;

byte[] doc1 = Files.readAllBytes(Paths.get("doc1.pdf"));
byte[] doc2 = Files.readAllBytes(Paths.get("doc2.docx"));

List<ExtractInput> items = Arrays.asList(
    new ExtractInput(doc1, "application/pdf", null),
    new ExtractInput(doc2, "application/vnd.openxmlformats-officedocument.wordprocessingml.document", null)
);

ExtractionConfig config = ExtractionConfig.builder().build();
List<ExtractionResult> results = Xberg.extractBatchSync(items, config);
System.out.println("Processed " + results.size() + " documents");
```
