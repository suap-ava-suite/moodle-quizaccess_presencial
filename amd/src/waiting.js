// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

import Ajax from 'core/ajax';

/**
 * Poll the release request without a manual refresh or check button.
 *
 * @param {number} requestId Request id.
 */
export const init = (requestId) => {
    const region = document.querySelector('[data-region="request-status"]');
    if (!region) {
        return;
    }
    const container = region.closest('.quizaccess-presencial-waiting');
    if (!container) {
        return;
    }
    const renderedState = container.getAttribute('data-state');

    const poll = () => {
        Ajax.call([{
            methodname: 'quizaccess_presencial_read_request',
            args: {requestid: requestId},
        }])[0].then((result) => {
            if (result.state !== renderedState) {
                window.location.reload();
                return;
            }
            if (['pending', 'authorized', 'starting'].includes(result.state)) {
                window.setTimeout(poll, 5000);
                return;
            }
            window.location.reload();
        }).catch(() => {
            window.setTimeout(poll, 5000);
        });
    };

    window.setTimeout(poll, 5000);
};
