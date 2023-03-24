<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
 */

declare(strict_types=1);

namespace OCA\Analytics\Search;

use OCA\Analytics\Service\ReportService;
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

    public function __construct(IAppManager $appManager,
                                IL10N $l10n,
                                IURLGenerator $urlGenerator,
                                ReportService $ReportService)
    {
        $this->appManager = $appManager;
        $this->l10n = $l10n;
        $this->urlGenerator = $urlGenerator;
        $this->ReportService = $ReportService;
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

        $datasets = $this->ReportService->search($query->getTerm());
        $result = [];

        foreach ($datasets as $dataset) {
            $result[] = new SearchResultEntry(
                $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('analytics', 'app-dark.svg')),
                $dataset['name'],
                '',
                $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('analytics.page.index') . '#/r/' . $dataset['id']),
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