<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Activity;


use OCP\IL10N;

class SettingData implements \OCP\Activity\ISetting
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
        return 'analytics_data';
    }

    /**
     * @return string A translated string
     * @since 11.0.0
     */
    public function getName()
    {
        return $this->l->t('<strong>Analytics</strong>: New data was added to a report');
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
        return false;
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