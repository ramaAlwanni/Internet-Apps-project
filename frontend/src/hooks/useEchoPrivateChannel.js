import { useEffect, useRef } from "react";
import { echo } from "../lib/echo";

/**
 * Subscribe to a private channel and keep listeners stable across renders.
 *
 * @param {string | null | undefined} channelName Channel name *without* the
 *   "private-" prefix, e.g. "admin.orders" or `order.${orderId}`.
 * @param {Record<string, (payload: unknown) => void>} listeners Map of
 *   event names (with leading dot, e.g. ".order.created") to handlers.
 */
export function useEchoPrivateChannel(channelName, listeners) {
  // Keep the latest handlers without re-subscribing on every render.
  const listenersRef = useRef(listeners);

  useEffect(() => {
    listenersRef.current = listeners;
  });

  useEffect(() => {
    if (!channelName) return undefined;

    const channel = echo.private(channelName);
    const registered = Object.keys(listenersRef.current ?? {}).map((event) => {
      const handler = (payload) => {
        listenersRef.current?.[event]?.(payload);
      };

      channel.listen(event, handler);

      return { event, handler };
    });

    return () => {
      registered.forEach(({ event, handler }) => {
        channel.stopListening(event, handler);
      });

      // Leave the exact channel ("private-admin.orders", "private-order.42", ...).
      echo.leaveChannel(channel.name);
    };
  }, [channelName]);
}

export default useEchoPrivateChannel;
