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

namespace OCA\Analytics\Dashboard;

use OCP\Dashboard\IWidget;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Util;

class Widget implements IWidget
{

    /** @var IURLGenerator */
    private $url;
    /** @var IL10N */
    private $l10n;

    public function __construct(
        IURLGenerator $url,
        IL10N $l10n
    )
    {
        $this->url = $url;
        $this->l10n = $l10n;
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return 'analytics';
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return $this->l10n->t('Reports');
    }

    /**
     * @inheritDoc
     */
    public function getOrder(): int
    {
        return 10;
    }

    /**
     * @inheritDoc
     */
    public function getIconClass(): string
    {
        return 'icon-analytics';
    }

    /**
     * @inheritDoc
     */
    public function getUrl(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function load(): void
    {
        Util::addScript('analytics', 'dashboard');
        Util::addScript('analytics', 'visualization');
        Util::addStyle('analytics', 'dashboard');
        Util::addScript('analytics', '3rdParty/chart.min');
        Util::addScript('analytics', '3rdParty/chartjs-adapter-moment');
        Util::addScript('analytics', '3rdParty/moment');
        Util::addScript('analytics', '3rdParty/cloner');
        Util::addScript('analytics', '3rdParty/chartjs-plugin-datalabels.min');
    }
}