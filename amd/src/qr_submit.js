// This file is part of Moodle - http://moodle.org/
/**
 * Submit the secret with the authenticated operator's session key.
 *
 * @param {number} requestId Release request identifier.
 */
export const init = (requestId) => {
    const key = `presencial-qr-${requestId}`;
    document.getElementById('presencial-qr-token').value = window.sessionStorage.getItem(key) || '';
    window.sessionStorage.removeItem(key);
    document.getElementById('presencial-qr-submit').submit();
};
