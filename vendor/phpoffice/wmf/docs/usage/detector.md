# Detector

The `Detector` class detects the type of an image from its content : WMF, EMF, EMF+ or unknown.

| Constant                   | Value     | Description                                                       |
|----------------------------|-----------|-------------------------------------------------------------------|
| `Detector::TYPE_WMF`       | `wmf`     | WMF file (placeable or not)                                       |
| `Detector::TYPE_EMF`       | `emf`     | EMF file                                                          |
| `Detector::TYPE_EMFPLUS`   | `emf+`    | EMF file containing EMF+ records                                  |
| `Detector::TYPE_UNKNOWN`   | `unknown` | Other file                                                        |

!!! note "EMF+ files"

    EMF+ files are EMF files whose first record (after the header) is a EMF+ header.
    EMF+ "dual" files contain a EMF fallback : they can be read by the EMF readers.

## `detect`

The method returns the type of an image from its content.

```php
<?php

use PhpOffice\WMF\Reader\Detector;

$type = Detector::detect(file_get_contents('sample.emf'));

echo 'The type of sample.emf is ' . $type;
```

## `detectFile`

The method returns the type of an image file. Only the headers of the file are read.

```php
<?php

use PhpOffice\WMF\Reader\Detector;

switch (Detector::detectFile('sample')) {
    case Detector::TYPE_WMF:
        $reader = new PhpOffice\WMF\Reader\WMF\Magic();
        break;
    case Detector::TYPE_EMF:
    case Detector::TYPE_EMFPLUS:
        $reader = new PhpOffice\WMF\Reader\EMF\Magic();
        break;
    default:
        throw new Exception('Not supported');
}
$reader->load('sample');
```

## `isWMF`, `isPlaceableWMF`, `isEMF` & `isEMFPlus`

These methods return if the content is a WMF file (placeable or not), a placeable WMF file, a EMF file (including EMF+ files) or a EMF+ file.

```php
<?php

use PhpOffice\WMF\Reader\Detector;

$content = file_get_contents('sample.emf');

var_dump(Detector::isWMF($content));
var_dump(Detector::isPlaceableWMF($content));
var_dump(Detector::isEMF($content));
var_dump(Detector::isEMFPlus($content));
```
