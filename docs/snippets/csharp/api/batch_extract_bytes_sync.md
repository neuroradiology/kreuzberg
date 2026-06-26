```csharp title="C#"
using Xberg;

var items = new List<ExtractInput>
{
    new() { Content = await File.ReadAllBytesAsync("doc1.pdf"), MimeType = "application/pdf", Config = null },
    new() { Content = await File.ReadAllBytesAsync("doc2.txt"), MimeType = "text/plain", Config = null }
};

var config = new ExtractionConfig { OutputFormat = OutputFormat.Text };
var results = XbergLib.ExtractBatchSync(items, config);

foreach (var result in results)
{
    Console.WriteLine($"Content length: {result.Content.Length}");
}
```
