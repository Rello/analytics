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

namespace OCA\Analytics\Activity;

use OCP\IL10N;
use OCP\IURLGenerator;

class Filter implements \OCP\Activity\IFilter
{

    private $l10n;
    private $urlGenerator;

    public function __construct(
        IL10N $l10n,
        IURLGenerator $urlGenerator
    )
    {
        $this->l10n = $l10n;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @return string Lowercase a-z and underscore only identifier
     * @since 11.0.0
     */
    public function getIdentifier()
    {
        return 'analytics';
    }

    /**
     * @return string A translated string
     * @since 11.0.0
     */
    public function getName()
    {
        return $this->l10n->t('Analytics');
    }

    /**
     * @return int whether the filter should be rather on the top or bottom of
     * the admin section. The filters are arranged in ascending order of the
     * priority values. It is required to return a value between 0 and 100.
     * @since 11.0.0
     */
    public function getPriority()
    {
        return 90;
    }

    /**
     * @return string Full URL to an icon, empty string when none is given
     * @since 11.0.0
     */
    public function getIcon()
    {
        return $this->urlGenerator->imagePath('analytics', 'app-dark.svg');
    }

    /**
     * @param string[] $types
     * @return string[] An array of allowed apps from which activities should be displayed
     * @since 11.0.0
     */
    public function filterTypes(array $types)
    {
        return array_merge($types, ['analytics_report'], ['analytics_dataset'], ['analytics_data']);
    }

    /**
     * @return string[] An array of allowed apps from which activities should be displayed
     * @since 11.0.0
     */
    public function allowedApps()
    {
        return ['analytics'];
    }
}

