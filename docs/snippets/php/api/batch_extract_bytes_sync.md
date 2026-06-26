```php title="PHP"
<?php
declare(strict_types=1);

use Xberg\Xberg;
use Xberg\ExtractionConfig;
use Xberg\ExtractInput;

$config = new ExtractionConfig();
$items = [
    new ExtractInput('Hello, world!', 'text/plain'),
    new ExtractInput("# Heading\n\nParagraph text.", 'text/markdown'),
];
$results = Xberg::extractBatchSync($items, $config);

foreach ($results as $i => $result) {
    echo "Item $i: " . strlen($result->getContent()) . " chars\n";
}
```
