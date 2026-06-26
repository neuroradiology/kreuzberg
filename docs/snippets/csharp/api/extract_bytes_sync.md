```csharp title="C#"
using Xberg;

var data = File.ReadAllBytes("document.pdf");
var config = new ExtractionConfig { OutputFormat = OutputFormat.Text };
var result = XbergLib.ExtractSync(data, "application/pdf", config);

Console.WriteLine(result.Content);
Console.WriteLine($"MIME Type: {result.MimeType}");
```
