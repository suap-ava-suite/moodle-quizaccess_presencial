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
    const messageRegion = container.querySelector('[data-region="request-message"]');
    const messages = messageRegion.dataset;
    const reasonRegion = container.querySelector('[data-region="request-reason"]');
    const announcement = container.querySelector('[data-region="request-announcement"]');
    const pollingMessage = container.querySelector('[data-region="request-polling"]');
    const startForm = container.querySelector('[data-region="request-start"]');
    const returnRegion = container.querySelector('[data-region="request-return"]');
    let renderedState = container.getAttribute('data-state');
    // HTML normalizes textarea CRLF line endings; compare without changing the stored explanation.
    const normalizeReason = (reason) => reason.replace(/\r\n?/g, '\n');
    let renderedReason = normalizeReason(container.getAttribute('data-rejection-reason') || '');

    const connectionRegion = container.querySelector('[data-region="request-connection"]');
    let currentWarning = '';
    const showWarning = (message = '') => {
        if (!connectionRegion || message === currentWarning) {
            return;
        }
        currentWarning = message;
        connectionRegion.textContent = message;
        connectionRegion.classList.toggle('alert', message !== '');
        pollingMessage.hidden = message !== '' || !['pending', 'starting'].includes(renderedState);
    };

    const qrRegion = document.querySelector('[data-region="request-qr"]');
    const qrImage = document.querySelector('[data-region="request-qr-image"]');
    const canIssueQr = () => ['pending', 'authorized', 'rejected'].includes(renderedState);
    let qrTimer;
    let qrGeneration = 0;
    const refreshQr = () => {
        if (!qrRegion || !qrImage || !canIssueQr()) {
            return;
        }
        const startedAt = Date.now();
        const generation = ++qrGeneration;
        qrRegion.hidden = true;
        Ajax.call([{
            methodname: 'quizaccess_presencial_issue_qr',
            args: {requestid: requestId},
        }])[0].then((result) => {
            // A late QR response must not revive an expired or starting request.
            if (canIssueQr() && generation === qrGeneration) {
                qrImage.src = result.image;
                qrRegion.hidden = false;
                qrTimer = window.setTimeout(refreshQr, Math.max(0, 60000 - (Date.now() - startedAt)));
            }
            return result;
        }).catch(() => {
            if (canIssueQr() && generation === qrGeneration) {
                qrRegion.hidden = true;
                qrTimer = window.setTimeout(refreshQr, 5000);
            }
        });
    };

    const applyStatus = (result) => {
        const reason = normalizeReason(result.rejectionreason);
        if (result.state === renderedState && reason === renderedReason) {
            return;
        }
        const activeElement = document.activeElement;
        const authorized = result.state === 'authorized';
        const pending = ['pending', 'starting'].includes(result.state);
        const losesFocus = (!authorized && startForm.contains(activeElement)) ||
            (authorized && returnRegion.contains(activeElement));
        const hadQr = canIssueQr();

        messageRegion.textContent = messages[result.state];
        reasonRegion.textContent = result.state === 'rejected' ? result.rejectionreason : '';
        reasonRegion.hidden = result.state !== 'rejected';
        startForm.hidden = !authorized;
        returnRegion.hidden = authorized;
        pollingMessage.hidden = !pending || currentWarning !== '';
        region.classList.toggle('alert-info', pending);
        region.classList.toggle('alert-success', authorized);
        region.classList.toggle('alert-warning', ['rejected', 'expired'].includes(result.state));
        renderedState = result.state;
        renderedReason = reason;
        container.dataset.state = renderedState;
        container.dataset.rejectionReason = result.rejectionreason;

        if (!canIssueQr()) {
            window.clearTimeout(qrTimer);
            qrGeneration++;
            if (qrRegion) {
                qrRegion.hidden = true;
            }
        } else if (!hadQr) {
            refreshQr();
        }

        // Announce through focus or the live region, avoiding two announcements of the same change.
        if (losesFocus) {
            announcement.textContent = '';
            region.focus();
        } else {
            const message = [messageRegion.textContent, reasonRegion.textContent].filter(Boolean).join(' ');
            if (announcement.textContent !== message) {
                announcement.textContent = message;
            }
        }
    };

    const poll = () => {
        Ajax.call([{
            methodname: 'quizaccess_presencial_read_request',
            args: {requestid: requestId},
        }], true, true, false, 10000)[0].then((result) => {
            showWarning();
            if (result.state === 'consumed') {
                // Only resume an attempt which another tab or device has already created.
                window.location.reload();
                return result;
            }
            applyStatus(result);
            if (['pending', 'authorized', 'rejected', 'starting'].includes(result.state)) {
                window.setTimeout(poll, 5000);
            }
            return result;
        }).catch((error) => {
            const {message, updateError} = connectionRegion?.dataset ?? {};
            showWarning(error?.errorcode ? updateError : message);
            window.setTimeout(poll, 5000);
        });
    };

    refreshQr();
    window.setTimeout(poll, 5000);
};
