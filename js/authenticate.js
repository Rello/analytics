/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

document.addEventListener('DOMContentLoaded', function () {
    $('#password').on('keyup input change', function () {
        if ($('#password').val().length > 0) {
            $('#password-submit').prop('disabled', false);
        } else {
            $('#password-submit').prop('disabled', true);
        }
    });
});
