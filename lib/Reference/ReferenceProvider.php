<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Reference;

use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Service\PanoramaService;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OC\Collaboration\Reference\ReferenceManager;
use OCP\Collaboration\Reference\IReference;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class ReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider
{
    private const RICH_OBJECT_TYPE = 'analytics'; // 'open-graph'

    private ?string $userId;
    private IConfig $config;
    private ReferenceManager $referenceManager;
    private IL10N $l10n;
    private IURLGenerator $urlGenerator;
    private LoggerInterface $logger;
    private ReportService $ReportService;
    private PanoramaService $PanoramaService;

    public function __construct(IConfig          $config,
                                LoggerInterface  $logger,
                                IL10N            $l10n,
                                IURLGenerator    $urlGenerator,
                                ReferenceManager $referenceManager,
                                ReportService    $ReportService,
                                PanoramaService  $PanoramaService,
                                ?string          $userId)
    {
        $this->userId = $userId;
        $this->logger = $logger;
        $this->config = $config;
        $this->referenceManager = $referenceManager;
        $this->l10n = $l10n;
        $this->urlGenerator = $urlGenerator;
        $this->ReportService = $ReportService;
        $this->PanoramaService = $PanoramaService;
    }

    public function getId(): string
    {
        return 'analytics';
    }

    public function getTitle(): string
    {
        return $this->l10n->t('Analytics');
    }

    public function getOrder(): int
    {
        return 10;
    }

    public function getIconUrl(): string
    {
        return $this->urlGenerator->getAbsoluteURL(
            $this->urlGenerator->imagePath('analytics', 'app-dark.svg')
        );
    }

    public function getSupportedSearchProviderIds(): array
    {
        return ['analytics'];
    }

    public function matchReference(string $referenceText): bool
    {
        $adminLinkPreviewEnabled = $this->config->getAppValue('analytics', 'link_preview_enabled', '1') === '1';
        if (!$adminLinkPreviewEnabled) {
            //return false;
        }
        return preg_match('~/apps/analytics/(?:r|pa)/~', $referenceText) === 1;
    }

    public function resolveReference(string $referenceText): ?IReference
    {
        if ($this->matchReference($referenceText)) {
            preg_match("/\d+$/", $referenceText, $matches); // get the last integer
            $isPanorama = str_contains($referenceText, '/pa/');
            if ($isPanorama) {
                $item = $this->PanoramaService->read((int)$matches[0]);
                $icon = 'panorama.svg';
				$type = $this->l10n->t('Panorama');
            } else {
                $item = $this->ReportService->read((int)$matches[0]);
                $icon = 'report.svg';
				$type = $this->l10n->t('Report');
            }
            if (!empty($item)) {
                $imageUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('analytics', $icon));
                $name = $this->l10n->t('Analytics') . ' ' . $type;
                $subheader = $item['name'];
            } else {
                $imageUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('analytics', 'noReport.svg'));
                $name = $this->l10n->t('Report not found');
                $subheader = $this->l10n->t('This report was removed or is not shared with you');
            }
            $reference = new Reference($referenceText);
            $reference->setTitle($name);
            $reference->setDescription($subheader);
            $reference->setImageUrl($imageUrl);
            $reference->setRichObject(
                self::RICH_OBJECT_TYPE,
                [
                    'name' => $name,
                    'subheader' => $subheader,
                    'url' => $referenceText,
                    'image' => $imageUrl
                ]
            );
            return $reference;
        }
        return null;
    }

    public function getCachePrefix(string $referenceId): string
    {
        return $this->userId ?? '';
    }

    public function getCacheKey(string $referenceId): ?string
    {
        return $referenceId;
    }

    public function invalidateUserCache(string $userId): void
    {
        $this->referenceManager->invalidateCache($userId);
    }
}