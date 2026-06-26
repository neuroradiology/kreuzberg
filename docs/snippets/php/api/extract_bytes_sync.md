```php title="PHP"
<?php
declare(strict_types=1);

use Xberg\Xberg;
use Xberg\ExtractionConfig;

$content = file_get_contents('document.pdf');
$config = new ExtractionConfig();
$result = Xberg::extractSync($content, 'application/pdf', $config);

echo $result->getContent();
echo 'Tables: ' . count($result->getTables()) . "\n";
```
