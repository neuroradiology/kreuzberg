```php title="PHP"
<?php
declare(strict_types=1);

use Xberg\Xberg;
use Xberg\ExtractionConfig;
use Xberg\ExtractInput;

$config = new ExtractionConfig();
$items = [
    new ExtractInput('doc1.pdf'),
    new ExtractInput('doc2.docx'),
    new ExtractInput('report.pdf'),
];
$results = Xberg::extractBatchSync($items, $config);

foreach ($results as $i => $result) {
    echo "File $i: " . strlen($result->getContent()) . " chars\n";
}
```
