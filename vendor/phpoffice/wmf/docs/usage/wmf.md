# WMF

You can load `.wmf` files.

## Backends

You can one of two current backends : `gd` or `imagick`.
If you don't know which one used, you can use the magic one.

By default, the order of the backends is Imagick, followed by GD.
Each backend is tested on different criteria: extension loaded, format support.

```php
<?php

use PhpOffice\WMF\Reader\WMF\GD;
use PhpOffice\WMF\Reader\WMF\Imagick;
use PhpOffice\WMF\Reader\WMF\Magic;

// Choose which backend you want
$reader = new GD();
$reader = new Imagick();
$reader = new Magic();

$reader->load('sample.wmf');
```

For next samples, I will use the magic one.

### `getBackends`

This specific method for `Magic::class` returns backends sorted by priority.

```php
<?php

use PhpOffice\WMF\Reader\WMF\Magic;

$reader = new Magic();

var_dump($reader->getBackends());
```

### `setBackends`

This specific method for `Magic::class` defines backends sorted by priority.

```php
<?php

use PhpOffice\WMF\Reader\WMF\GD;
use PhpOffice\WMF\Reader\WMF\Imagick;
use PhpOffice\WMF\Reader\WMF\Magic;

$reader = new Magic();
$reader->setBackends([
  GD::class,
  Imagick::class,
]);

var_dump($reader->getBackends());
```

## GD backend

The `GD` backend is a renderer written in pure PHP, shared with the EMF reader (`PhpOffice\WMF\Renderer\GD`).
The image is generated at 72 DPI, based on the bounding box of the placeable header.
For WMF files without placeable header, the bounding box is the first window of the file (`META_SETWINDOWORG` & `META_SETWINDOWEXT`),
and the number of logical units per inch depends on the map mode (`META_SETMAPMODE`) : 1440 for `MM_ANISOTROPIC` & `MM_ISOTROPIC`.
The window of the file is mapped to the image : the map mode and the viewport are ignored.

It supports :

- shapes (polygons, polylines, rectangles, rounded rectangles, ellipses, arcs, chords, pies)
- pens (width, end caps, joins, dashes) & brushes (solid, hatched, pattern brushes)
- regions & clipping
- bitmaps (`META_STRETCHDIB`, `META_DIBBITBLT`, `META_DIBSTRETCHBLT`, `META_BITBLT`, `META_STRETCHBLT`, `META_SETDIBTODEV`) & flood fills
- texts (`META_TEXTOUT`, `META_EXTTEXTOUT`), with TrueType/OpenType fonts

If a not supported record is found (like `META_DRAWTEXT`), or a bitmap which is not supported (like CMYK bitmaps, `BI_CMYK`),
an exception is thrown (or `load` returns `false`, if exceptions are disabled).
Corrupted files throw an exception too.

### Known limitations

- WMF files without placeable header must define a window (`META_SETWINDOWEXT`) : else, an exception is thrown.
- The background of the image is white by default (see [`setBackgroundColor`](#setbackgroundcolor)).
- The map mode & the viewport (`META_SETMAPMODE`, `META_SETVIEWPORTORG`, `META_SETVIEWPORTEXT`...) are ignored.
- Pens :
    - pens have a minimal width of one pixel, and dashes have flat caps.
- Brushes :
    - the patterns of pattern brushes are aligned on the origin of the image (the brush origin is ignored).
- Regions : `META_FRAMEREGION` draws the frame of each rectangle of the region.
- Bitmaps :
    - CMYK bitmaps (`BI_CMYK`, `BI_CMYKRLE4` & `BI_CMYKRLE8`) are not supported : an exception is thrown,
    - the pixels skipped by compressed bitmaps (`BI_RLE4` & `BI_RLE8`) are transparent,
    - bitmaps using the palette of the file (`DIB_PAL_COLORS`) and indexed device dependent bitmaps (`Bitmap16`) are drawn with a gray scale,
      and monochrome `Bitmap16` are drawn in black & white,
    - raster operations are applied on the image : a transparent background is used as a white background,
    - corrupted JPEG & PNG bitmaps are not drawn.
- Flood fills (`META_FLOODFILL` & `META_EXTFLOODFILL`) ignore the clipping region.
- Texts :
    - texts are not drawn if no font is found (see [Fonts](#fonts)),
    - the metrics of fonts are approximated (ascent, descent), the width & the orientation of fonts are ignored,
    - the charset is ignored : texts are decoded as Windows-1252,
    - the characters of the `Symbol` font are converted to Unicode (Greek letters, mathematical symbols...),
      other symbol fonts (like `Wingdings`) are drawn with another font.
- `META_SETROP2`, `META_SETSTRETCHBLTMODE`, `META_ESCAPE` & palettes are ignored.

### Fonts

Texts are drawn with the TrueType/OpenType fonts (`.ttf` & `.otf`) found in the system directories.
You can define the directories where fonts are searched (recursively) with `setFontDirectories`.

See [EMF > Fonts](emf.md#fonts) for more details.

```php
<?php

use PhpOffice\WMF\Reader\WMF\GD;

$reader = new GD();
$reader->setFontDirectories([
    __DIR__ . '/fonts',
]);
$reader->load('sample.wmf');
```

## Methods

### `getResource`

The method returns the resource used in internal by the library.

The `GD` backend returns a `GDImage` object or resource, depending the PHP version.
The `Imagick` backend returns a `Imagick` object.

```php
<?php

use PhpOffice\WMF\Reader\WMF\Magic;

$reader = new Magic();
$reader->load('sample.wmf');

var_dump($reader->getResource());
```

### `getMediaType`

The method returns the media type for a WMF file.

```php
<?php

use PhpOffice\WMF\Reader\WMF\Magic;

$reader = new Magic();

$mediaType = $reader->getMediaType();

echo 'The media type for a WMF file is ' . $$mediaType;
```

### `setBackgroundColor`

The method defines the color of the background (white by default), or a transparent background (`null`).
The background must be defined before loading the file.
Formats without alpha channel (`gif`, `jpg` & `wbmp`) are saved with a white background.

```php
<?php

use PhpOffice\WMF\Reader\WMF\GD;

$reader = new GD();
// Transparent background
$reader->setBackgroundColor(null);
// Blue background
$reader->setBackgroundColor([0, 0, 255]);
$reader->load('sample.wmf');
```

### `isWMF`

The method returns if the file is supported by the library.

```php
<?php

use PhpOffice\WMF\Reader\WMF\Magic;

$reader = new Magic();
$reader->load('sample.wmf');

$isWMF = $reader->isWMF();

echo 'The file sample.wmf ' . ($isWMF ? 'is a WMF file' : 'is not a WMF file');
```

### `isSupported`

The method returns if the reader can be used : the extension is loaded (and supports the format, for `Imagick`).
For `Magic::class`, the method returns if one of its backends can be used.

```php
<?php

use PhpOffice\WMF\Reader\WMF\Imagick;

$reader = new Imagick();

echo 'The Imagick backend ' . ($reader->isSupported() ? 'can' : 'can not') . ' be used';
```

### `load`

The method loads a WMF file in the object.
The method returns `true` if the file has been correctly loaded, or `false` if it has not. 

```php
<?php

use PhpOffice\WMF\Reader\WMF\Magic;

$reader = new Magic();
$reader->load('sample.wmf');
```

### `loadFromString`

The method loads a WMF file in the object from a string.
The method returns `true` if the file has been correctly loaded, or `false` if it has not. 

```php
<?php

use PhpOffice\WMF\Reader\WMF\Magic;

$reader = new Magic();
$reader->loadFromString(file_get_contents('sample.wmf'));
```

### `save`

The method transforms the loaded WMF file in an another image. 

```php
<?php

use PhpOffice\WMF\Reader\WMF\Magic;

$reader = new Magic();
$reader->load('sample.wmf');
$reader->save('sample.png', 'png');
```
