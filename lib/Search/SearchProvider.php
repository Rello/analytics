<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Search;

use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Service\PanoramaService;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class SearchProvider implements IProvider
{

    /** @var IAppManager */
    private $appManager;
    /** @var IL10N */
    private $l10n;
    /** @var IURLGenerator */
    private $urlGenerator;
    private $ReportService;
    private $PanoramaService;

    public function __construct(IAppManager $appManager,
                                IL10N $l10n,
                                IURLGenerator $urlGenerator,
                                ReportService $ReportService,
                                PanoramaService $PanoramaService)
    {
        $this->appManager = $appManager;
        $this->l10n = $l10n;
        $this->urlGenerator = $urlGenerator;
        $this->ReportService = $ReportService;
        $this->PanoramaService = $PanoramaService;
    }

    public function getId(): string
    {
        return 'analytics';
    }

    public function search(IUser $user, ISearchQuery $query): SearchResult
    {
        if (!$this->appManager->isEnabledForUser('analytics', $user)) {
            return SearchResult::complete($this->getName(), []);
        }

        $reports = $this->ReportService->search($query->getTerm());
        $panoramas = $this->PanoramaService->search($query->getTerm());
        $result = [];

        foreach ($reports as $report) {
            $result[] = new SearchResultEntry(
                $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('analytics', 'report.svg')),
                $report['name'],
                '',
                $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('analytics.page.report', ['id' => $report['id']])),
                ''
            );
        }

        foreach ($panoramas as $panorama) {
            $result[] = new SearchResultEntry(
                $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('analytics', 'panorama.svg')),
                $panorama['name'],
                '',
                $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('analytics.page.panorama', ['id' => $panorama['id']])),
                ''
            );
        }

        return SearchResult::complete(
            $this->l10n->t('Analytics'),
            $result
        );
    }

    public function getName(): string
    {
        return $this->l10n->t('Analytics');
    }

    public function getOrder(string $route, array $routeParameters): int
    {
        return 10;
    }
}