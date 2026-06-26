```csharp title="C#"
using Xberg;

var items = new List<ExtractInput>
{
    new() { Path = "document1.pdf", Config = null },
    new()
    {
        Path = "document2.pdf",
        Config = new FileExtractionConfig { ForceOcr = true }
    }
};

var config = new ExtractionConfig { OutputFormat = OutputFormat.Text };
var results = XbergLib.ExtractBatchSync(items, config);

foreach (var result in results)
{
    Console.WriteLine($"Content length: {result.Content.Length}");
}
```
