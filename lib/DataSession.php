<?php
declare(strict_types=1);
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
 */

namespace OCA\Analytics;

use OCP\ISession;

class DataSession
{
    /** @var ISession */
    protected $session;

    public function __construct(ISession $session)
    {
        $this->session = $session;
    }

    public function getPasswordForShare(string $token): ?string
    {
        return $this->getValue('data-password', $token);
    }

    protected function getValue(string $key, string $token): ?string
    {
        $values = $this->getValues($key);
        if (!isset($values[$token])) {
            return null;
        }
        return $values[$token];
    }

    protected function getValues(string $key): array
    {
        $values = $this->session->get($key);
        if ($values === null) {
            return [];
        }
        $values = json_decode($values, true);
        if ($values === null) {
            return [];
        }
        return $values;
    }

    public function setPasswordForShare(string $token, string $password): void
    {
        $this->setValue('data-password', $token, $password);
    }

    protected function setValue(string $key, string $token, string $value): void
    {
        $values = $this->session->get($key);
        if ($values === null) {
            $values = [];
        } else {
            $values = json_decode($values, true);
            if ($values === null) {
                $values = [];
            }
        }
        $values[$token] = $value;
        $this->session->set($key, json_encode($values));
    }

    public function removePasswordForShare(string $token): void
    {
        $this->removeValue('data-password', $token);
    }

    protected function removeValue(string $key, string $token): void
    {
        $values = $this->session->get($key);
        if ($values === null) {
            $values = [];
        } else {
            $values = json_decode($values, true);
            if ($values === null) {
                $values = [];
            } else {
                unset($values[$token]);
            }
        }
        $this->session->set($key, json_encode($values));
    }
}