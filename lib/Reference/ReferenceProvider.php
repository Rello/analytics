<?php
namespace OCA\Analytics\Reference;

use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OC\Collaboration\Reference\ReferenceManager;
use OCP\Collaboration\Reference\IReference;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class ReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {

    private const RICH_OBJECT_TYPE = 'analytics';

    private ?string $userId;
    private IConfig $config;
    private ReferenceManager $referenceManager;
    private IL10N $l10n;
    private IURLGenerator $urlGenerator;
    private LoggerInterface $logger;

    public function __construct(IConfig $config,
                                LoggerInterface $logger,
                                IL10N $l10n,
                                IURLGenerator $urlGenerator,
                                ReferenceManager $referenceManager,
                                ?string $userId) {
        $this->userId = $userId;
        $this->logger = $logger;
        $this->config = $config;
        $this->referenceManager = $referenceManager;
        $this->l10n = $l10n;
        $this->urlGenerator = $urlGenerator;
    }

    public function getId(): string	{
        return 'analytics';
    }

    public function getTitle(): string {
        return $this->l10n->t('Analytics Reference');
    }

    public function getOrder(): int	{
        return 10;
    }

    public function getIconUrl(): string {
        return $this->urlGenerator->getAbsoluteURL(
            $this->urlGenerator->imagePath('analytics', 'app-dark.svg')
        );
    }

    public function getSupportedSearchProviderIds(): array {
        return ['analytics'];

    }

    public function matchReference(string $referenceText): bool {
        $this->logger->error('matchReference');
        $adminLinkPreviewEnabled = $this->config->getAppValue('analytics', 'link_preview_enabled', '1') === '1';
        if (!$adminLinkPreviewEnabled) {
            //return false;
        }
        return preg_match('~/apps/analytics~', $referenceText) === 1;
    }

    public function resolveReference(string $referenceText): ?IReference {
        $this->logger->error('resolveReference');
        if ($this->matchReference($referenceText)) {
            $this->logger->error('resolveReference - match positive');
                $reference = new Reference($referenceText);
                $reference->setTitle($this->l10n->t('Title'));
                $reference->setDescription($this->l10n->t('Description'));
                $imageUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('analytics', 'app.svg'));
                $reference->setImageUrl($imageUrl);
                $reference->setRichObject(
                    self::RICH_OBJECT_TYPE,
                );
                return $reference;
            }
        return null;
    }

    public function getCachePrefix(string $referenceId): string {
        return $this->userId ?? '';
    }

    public function getCacheKey(string $referenceId): ?string {
        return $referenceId;
    }

    public function invalidateUserCache(string $userId): void {
        $this->referenceManager->invalidateCache($userId);
    }
}