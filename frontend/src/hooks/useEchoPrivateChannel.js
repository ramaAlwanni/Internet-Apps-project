import { useEffect, useRef } from "react";
import { echo } from "../lib/echo";

/**
 * How many mounted components currently listen to each channel. Echo keeps a
 * single channel instance per name, so leaving it from one component would
 * silently unsubscribe every other component listening to the same channel.
 */
const channelSubscribers = new Map();

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
    channelSubscribers.set(
      channelName,
      (channelSubscribers.get(channelName) ?? 0) + 1,
    );

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

      const remaining = (channelSubscribers.get(channelName) ?? 1) - 1;

      if (remaining > 0) {
        channelSubscribers.set(channelName, remaining);
        return;
      }

      channelSubscribers.delete(channelName);

      // Leave the exact channel ("private-admin.orders", "private-order.42").
      echo.leaveChannel(channel.name);
    };
  }, [channelName]);
}

export default useEchoPrivateChannel;
