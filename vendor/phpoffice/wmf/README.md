# WMF

[![Latest Stable Version](https://poser.pugx.org/phpoffice/wmf/v)](https://packagist.org/packages/phpoffice/wmf)
[![Coverage Status](https://coveralls.io/repos/github/PHPOffice/WMF/badge.svg?branch=master)](https://coveralls.io/github/PHPOffice/WMF?branch=master)
[![Total Downloads](https://poser.pugx.org/phpoffice/wmf/downloads)](https://packagist.org/packages/phpoffice/wmf)
[![License](https://poser.pugx.org/phpoffice/wmf/license)](https://packagist.org/packages/phpoffice/wmf)
[![CI](https://github.com/PHPOffice/WMF/actions/workflows/php.yml/badge.svg)](https://github.com/PHPOffice/WMF/actions/workflows/php.yml)

WMF is a library written in pure PHP that provides a set of classes to read WMF based file (like `.wmf` or `.emf` files).

WMF is an open source project licensed under the terms of [MIT](https://github.com/PHPOffice/WMF/blob/master/LICENCE). WMF is aimed to be a high quality software product by incorporating [continuous integration and unit testing](https://github.com/PHPOffice/WMF/actions/workflows/php.yml). You can learn more about WMF by reading this Developers'Documentation and the [API Documentation](http://phpoffice.github.io/WMF/docs/develop/)

If you have any questions, please ask on [StackOverFlow](https://stackoverflow.com/questions/tagged/phpoffice-wmf)

Read more about WMF:

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Contributing](#contributing)
- [Developers' Documentation](https://phpoffice.github.io/WMF/)

## Features

- Read files

    * WMF files
    * EMF files
    * EMF+ files

## Requirements

WMF requires the following:

- PHP 7.1+

## Installation

WMF is installed via [Composer](https://getcomposer.org/).
To [add a dependency](https://getcomposer.org/doc/04-schema.md#package-links) to WMF in your project, either

Run the following to use the latest stable version
```sh
composer require phpoffice/wmf
```
or if you want the latest unreleased version
```sh
composer require phpoffice/wmf:dev-master
```

## Credits

The EMF test files of the directory `tests/resources/libemf2svg` come from the [libemf2svg](https://github.com/kakwa/libemf2svg/tree/master/tests/resources) project, licensed under the [GNU General Public License v2.0](https://github.com/kakwa/libemf2svg/blob/master/LICENSE).
The EMF test file `tests/resources/phpspreadsheet/issue274.emf` comes from the issue [PHPOffice/PhpSpreadsheet#274](https://github.com/PHPOffice/PhpSpreadsheet/issues/274).
They are only used by the test suite and are not distributed in the Composer package.

## Contributing

We welcome everyone to contribute to PHPWord. Below are some of the things that you can do to contribute.

- [Fork us](https://github.com/PHPOffice/WMF/fork) and [request a pull](https://github.com/PHPOffice/WMF/pulls) to the [master](https://github.com/PHPOffice/WMF/tree/master) branch.
- Submit [bug reports or feature requests](https://github.com/PHPOffice/WMF/issues) to GitHub.
- Follow [@PHPOffice](https://twitter.com/PHPOffice) on Twitter.
