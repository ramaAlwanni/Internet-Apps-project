/**
 * Per-tab auth storage.
 *
 * localStorage is shared by every tab of the same origin, so logging in with
 * another account in a second tab silently replaced the JWT of the first tab.
 * Echo and the API client then sent the wrong token (e.g. a customer token on
 * the admin broadcasting channel) and got a 403.
 *
 * sessionStorage is scoped to one tab, so every tab keeps its own JWT/user.
 */

const TOKEN_KEY = "token";
const USER_KEY = "user";

export function getToken() {
    try {
        return sessionStorage.getItem(TOKEN_KEY);
    } catch {
        return null;
    }
}

export function setToken(token) {
    try {
        sessionStorage.setItem(TOKEN_KEY, token);
    } catch {
        /* storage unavailable */
    }
}

export function getUser() {
    try {
        const raw = sessionStorage.getItem(USER_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

export function setUser(user) {
    try {
        sessionStorage.setItem(USER_KEY, JSON.stringify(user));
    } catch {
        /* storage unavailable */
    }
}

export function clearAuth() {
    try {
        sessionStorage.removeItem(TOKEN_KEY);
        sessionStorage.removeItem(USER_KEY);
        // Drop any legacy shared session so an old tab cannot resurrect it.
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
    } catch {
        /* storage unavailable */
    }
}
