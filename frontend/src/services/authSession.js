/**
 * Centralised handling for auth failures coming from the API client.
 *
 * The app keeps the JWT and user snapshot in localStorage, so when the token
 * expires (or no longer matches the server) every screen keeps rendering as if
 * the user were still signed in until an API call fails. These helpers clear
 * that stale session and send the user back to the login page with a clear
 * message instead of a raw `401`/`403` error in the UI.
 */

const SESSION_ENDED_KEY = "sessionEnded";

// Protects against redirects triggered by a burst of parallel requests.
let lastHandledAt = 0;

/** @returns {object|null} The user snapshot stored with the token. */
export function getStoredUser() {
    try {
        return JSON.parse(localStorage.getItem("user"));
    } catch {
        return null;
    }
}

export function clearSession() {
    localStorage.removeItem("token");
    localStorage.removeItem("user");
}

/**
 * Clears the stored session and returns the user to the login page.
 *
 * A hard redirect is intentional: it resets the Redux store and tears down
 * every Echo subscription so no stale listeners survive the expired session.
 *
 * @param {"expired"|"forbidden"} reason Stored for the login page to explain
 *   why the user was signed out.
 */
export function endSession(reason = "expired") {
    const now = Date.now();
    if (now - lastHandledAt < 1500) {
        return;
    }
    lastHandledAt = now;

    clearSession();

    // Already on the login page: nothing to redirect to.
    if (window.location.pathname === "/") {
        return;
    }

    try {
        sessionStorage.setItem(SESSION_ENDED_KEY, reason);
    } catch {
        // Storage may be unavailable; redirecting is still the right action.
    }

    window.location.replace("/");
}

/**
 * Reads (and clears) the reason left by `endSession`.
 *
 * @returns {"expired"|"forbidden"|null}
 */
export function consumeSessionEndedReason() {
    try {
        const reason = sessionStorage.getItem(SESSION_ENDED_KEY);
        if (reason) {
            sessionStorage.removeItem(SESSION_ENDED_KEY);
        }
        return reason;
    } catch {
        return null;
    }
}
