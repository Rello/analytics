<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics;

use OCP\Capabilities\ICapability;

class Capabilities implements ICapability {
    public function getCapabilities() {
        return [
            'declarativeui' => [
                'hooks' => [
                    [
                        'type' => 'context-menu',
                        'endpoints' => [
                            [
                                'name' => 'Show data in Analytics',
                                'url' => '/ocs/v2.php/apps/analytics/createFromDataFile',
                                'filter' => 'text/csv',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
