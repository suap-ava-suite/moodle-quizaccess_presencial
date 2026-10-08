// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Send the quiz view's ordinary start button through the post-validation request flow.
 *
 * @param {string} requestUrl Request endpoint URL.
 */
export const init = (requestUrl) => {
    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const button = target ? target.closest('.quizstartbuttondiv button[type="submit"]') : null;
        if (!button || button.closest('.quizsecuremoderequired') ||
                document.querySelector('#mod_quiz_preflight_form')) {
            return;
        }
        const form = button.closest('form');
        if (!form) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        form.action = requestUrl;
        form.submit();
    }, true);
};

/**
 * Submit an otherwise empty Moodle preflight form automatically. Required native inputs
 * remain user-controlled; Moodle validates them before notifying access rules.
 */
export const continue_preflight = () => {
    const form = document.querySelector('#mod_quiz_preflight_form');
    if (!form) {
        return;
    }
    const nativeControls = Array.from(form.querySelectorAll('input, select, textarea'))
        .filter((field) => !['hidden', 'submit', 'button', 'reset'].includes(field.type));
    if (nativeControls.length) {
        return;
    }
    const submit = form.querySelector('[name="submitbutton"]');
    if (submit) {
        submit.click();
    }
};
