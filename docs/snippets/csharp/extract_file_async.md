```csharp title="C#"
using Xberg;

var result = await XbergLib.ExtractAsync("document.pdf");

Console.WriteLine(result.Content);
Console.WriteLine(result.MimeType);
```
