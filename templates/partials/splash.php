<!--/**
* Analytics
*
* SPDX-FileCopyrightText: 2019-2024 Marcel Scherello
* SPDX-License-Identifier: AGPL-3.0-or-later
*/-->

<div
    id="app-splash-screen"
    class="app-splash-screen"
    data-splash
    hidden
    aria-hidden="true"
>
    <div class="app-splash-content">
        <div class="app-splash-logo" data-splash-logo aria-hidden="true">
            <img
                class="app-splash-logo-img"
                src="<?php p(\OC::$server->getURLGenerator()->imagePath('analytics', 'app-color.svg')); ?>"
                alt=""
                aria-hidden="true"
            >
        </div>
        <div class="app-splash-name" data-splash-name></div>
    </div>
</div>
