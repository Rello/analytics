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

class SettingDataset implements \OCP\Activity\ISetting
{

    /** @var IL10N */
    protected $l;

    /**
     * @param IL10N $l
     */
    public function __construct(IL10N $l)
    {
        $this->l = $l;
    }

    /**
     * @return string Lowercase a-z and underscore only identifier
     * @since 11.0.0
     */
    public function getIdentifier()
    {
        return 'analytics_report';
    }

    /**
     * @return string A translated string
     * @since 11.0.0
     */
    public function getName()
    {
        return $this->l->t('<strong>Analytics</strong>: New reports');
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
     * @return bool True when the option can be changed for the stream
     * @since 11.0.0
     */
    public function canChangeStream()
    {
        return true;
    }

    /**
     * @return bool True when the option can be changed for the stream
     * @since 11.0.0
     */
    public function isDefaultEnabledStream()
    {
        return true;
    }

    /**
     * @return bool True when the option can be changed for the mail
     * @since 11.0.0
     */
    public function canChangeMail()
    {
        return true;
    }

    /**
     * @return bool True when the option can be changed for the stream
     * @since 11.0.0
     */
    public function isDefaultEnabledMail()
    {
        return false;
    }
}