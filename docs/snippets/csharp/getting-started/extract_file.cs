using Xberg;

var result = await XbergClient.ExtractAsync("document.pdf");

Console.WriteLine(result.Content);
Console.WriteLine($"Tables: {result.Tables.Count}");
Console.WriteLine($"Metadata: {result.Metadata.FormatType}");
