// This file is part of Moodle - http://moodle.org/
/**
 * Preserve the secret across login without transmitting it in a URL.
 *
 * @param {number} requestId Release request identifier.
 */
export const init = (requestId) => {
    const token = window.location.hash.substring(1);
    window.sessionStorage.setItem(`presencial-qr-${requestId}`, token);
    window.location.replace(M.cfg.wwwroot + '/mod/quiz/accessrule/presencial/qr.php?requestid=' + requestId);
};
