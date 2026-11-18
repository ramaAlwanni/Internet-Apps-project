import { useDispatch, useSelector } from "react-redux";

import { useEchoPrivateChannel } from "../../../hooks/useEchoPrivateChannel";
import { orderStatusUpdated } from "../../../redux/slices/orderSlice";

/**
 * Tracks a single customer order in real time.
 *
 * `echo.private("order.42")` subscribes to `private-order.42`, which is
 * authorized by the `order.{orderId}` callback in routes/channels.php.
 *
 * The latest status is stored in Redux (`order.statusById`) so any component
 * rendering that order updates, not just the one holding the subscription.
 *
 * @param {number|string|null|undefined} orderId
 * @param {string|null|undefined} initialStatus
 * @returns {string|null} The latest known status for the order.
 */
export function useOrderStatusRealtime(orderId, initialStatus) {
  const dispatch = useDispatch();

  const realtimeStatus = useSelector((state) =>
    orderId ? state.order.statusById?.[String(orderId)] : undefined,
  );

  useEchoPrivateChannel(orderId ? `order.${orderId}` : null, {
    ".order.status.updated": (payload) => {
      const nextStatus = payload?.order?.status ?? payload?.status;
      if (!nextStatus) return;

      dispatch(
        orderStatusUpdated({
          orderId: Number(orderId),
          status: nextStatus,
        }),
      );
    },
  });

  return realtimeStatus ?? initialStatus ?? null;
}

export default useOrderStatusRealtime;
