```dart title="Dart"
import 'package:xberg/xberg.dart';

Future<void> main() async {
  final items = <ExtractInput>[
    const ExtractInput(path: 'doc1.pdf'),
    ExtractInput(
      path: 'scan.pdf',
      config: FileExtractionConfig(forceOcr: true),
    ),
  ];

  // Sync semantics — flutter_rust_bridge still returns a Future from Dart.
  final results = await XbergBridge.extractBatchSync(items);

  print('Processed ${results.length} files');
  for (final result in results) {
    print('${result.mimeType}: ${result.content.length} chars');
  }
}
```
