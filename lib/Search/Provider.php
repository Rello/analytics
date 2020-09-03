<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

declare(strict_types=1);

namespace OCA\Analytics\Search;

use OCA\Analytics\Controller\DatasetController;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class Provider implements IProvider
{

    /** @var IL10N */
    private $l10n;

    /** @var IURLGenerator */
    private $urlGenerator;

    private $datasetController;

    public function __construct(IL10N $l10n,
                                IURLGenerator $urlGenerator,
                                DatasetController $datasetController)
    {
        $this->l10n = $l10n;
        $this->urlGenerator = $urlGenerator;
        $this->datasetController = $datasetController;
    }

    public function getId(): string
    {
        return 'analytics';
    }

    public function search(IUser $user, ISearchQuery $query): SearchResult
    {
        $datasets = $this->datasetController->search($query->getTerm());

        foreach ($datasets as $dataset) {
            $result[] = new SearchResultEntry(
                '',
                $dataset['name'],
                '',
                $this->urlGenerator->linkToRoute('analytics.page.index') . '#/r/' . $dataset['id'],
                $this->urlGenerator->imagePath('analytics', 'app-dark.svg')
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