/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

document.addEventListener('DOMContentLoaded', function () {
    const passwordField = document.getElementById('password');
    const submitButton = document.getElementById('password-submit');

    if (!passwordField || !submitButton) {
        return;
    }

    const updateSubmitState = function () {
        submitButton.disabled = passwordField.value.length === 0;
    };

    passwordField.addEventListener('keyup', updateSubmitState);
    passwordField.addEventListener('input', updateSubmitState);
    passwordField.addEventListener('change', updateSubmitState);
    updateSubmitState();
});
