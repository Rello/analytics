<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$projectRoot = dirname(__DIR__);
$paths = [
    $projectRoot . '/vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer',
];

foreach ($paths as $path) {
    if (!file_exists($path)) {
        continue;
    }

    removePath($path);
    echo 'Removed ' . relativePath($projectRoot, $path) . PHP_EOL;
}

function removePath(string $path): void
{
    if (is_file($path) || is_link($path)) {
        if (!unlink($path)) {
            throw new RuntimeException('Could not remove file: ' . $path);
        }

        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $itemPath = $item->getPathname();

        if ($item->isDir() && !$item->isLink()) {
            if (!rmdir($itemPath)) {
                throw new RuntimeException('Could not remove directory: ' . $itemPath);
            }

            continue;
        }

        if (!unlink($itemPath)) {
            throw new RuntimeException('Could not remove file: ' . $itemPath);
        }
    }

    if (!rmdir($path)) {
        throw new RuntimeException('Could not remove directory: ' . $path);
    }
}

function relativePath(string $projectRoot, string $path): string
{
    $prefix = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (str_starts_with($path, $prefix)) {
        return substr($path, strlen($prefix));
    }

    return $path;
}
