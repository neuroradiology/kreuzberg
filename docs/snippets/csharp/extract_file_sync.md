```csharp title="C#"
using Xberg;

var result = XbergLib.ExtractSync("document.pdf", new ExtractionConfig());

Console.WriteLine(result.Content);
Console.WriteLine($"Tables: {result.Tables.Count}");
Console.WriteLine($"Metadata: {result.Metadata.FormatType}");
```
