import Echo from "laravel-echo";
import Pusher from "pusher-js";

// laravel-echo needs the Pusher client available globally.
window.Pusher = Pusher;

const scheme = import.meta.env.VITE_REVERB_SCHEME ?? "http";
const forceTLS = scheme === "https";

const wsHost = import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;
const wsPort = Number(import.meta.env.VITE_REVERB_PORT ?? (forceTLS ? 443 : 80));
const wssPort = Number(
  import.meta.env.VITE_REVERB_WSS_PORT ?? import.meta.env.VITE_REVERB_PORT ?? 443,
);

/**
 * Laravel's broadcasting auth endpoint.
 *
 * In this project the API lives under /api on a different port than the Vite
 * dev server, so the absolute URL is provided through VITE_BROADCAST_AUTH_ENDPOINT.
 * For a same-origin Sanctum SPA setup, you can omit it and keep the default
 * "/broadcasting/auth".
 */
const authEndpoint =
  import.meta.env.VITE_BROADCAST_AUTH_ENDPOINT ?? "/broadcasting/auth";

/**
 * Shared Laravel Echo instance configured for Laravel Reverb.
 *
 * Reverb speaks the Pusher protocol, so we use the "reverb" broadcaster
 * (backed by pusher-js) and disable HTTP polling transports.
 *
 * The custom authorizer sends the JWT/Sanctum bearer token when one is
 * available and also includes cookies, so the same instance works for:
 *
 *  - this project's JWT API guard (`Authorization: Bearer ...`)
 *  - a Sanctum SPA cookie/session setup (`credentials: "include"`)
 */
export const echo = new Echo({
  broadcaster: "reverb",
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost,
  wsPort,
  wssPort,
  forceTLS,
  enabledTransports: ["ws", "wss"],
  disableStats: true,
  authEndpoint,
  authorizer: (channel) => ({
    authorize: async (socketId, callback) => {
      try {
        const token = localStorage.getItem("token");

        const response = await fetch(authEndpoint, {
          method: "POST",
          // JWT requests do not need cookies. Fall back to cookies for a
          // Sanctum session setup (also enable supports_credentials in CORS).
          credentials: token ? "omit" : "include",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
          },
          body: JSON.stringify({
            socket_id: socketId,
            channel_name: channel.name,
          }),
        });

        if (!response.ok) {
          throw new Error(`Broadcast auth failed with status ${response.status}`);
        }

        callback(null, await response.json());
      } catch (error) {
        callback(error, null);
      }
    },
  }),
});

export default echo;
