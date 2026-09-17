<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Search;

use OCA\Analytics\Service\ReportService;

/** Render-mode choices for the native Smart Picker search dropdown. */
class ReferenceSearchProvider extends SearchProvider
{
    public const ID = 'analytics-reference';

    public function getId(): string
    {
        return self::ID;
    }

    public function getOrder(string $route, array $routeParameters): ?int
    {
        // Smart Picker queries this provider directly; keep global search unduplicated.
        return null;
    }

    protected function getReportModes(array $report): array
    {
        if ((int)($report['type'] ?? -1) === ReportService::REPORT_TYPE_GROUP) {
            return [];
        }

        $modes = ['' => $this->l10n->t('Link')];
        switch ($report['visualization'] ?? '') {
            case 'ct':
                $modes['content'] = $this->l10n->t('Chart and table');
                $modes['chart'] = $this->l10n->t('Chart only');
                $modes['table'] = $this->l10n->t('Table only');
                break;
            case 'chart':
                $modes['chart'] = $this->l10n->t('Chart only');
                break;
            case 'table':
                $modes['table'] = $this->l10n->t('Table only');
                break;
        }
        return $modes;
    }

    protected function getPanoramaModes(): array
    {
        return ['' => $this->l10n->t('Link'), 'content' => $this->l10n->t('Content')];
    }
}
