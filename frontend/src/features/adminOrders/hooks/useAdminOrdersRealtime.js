import { useDispatch } from "react-redux";

import { useEchoPrivateChannel } from "../../../hooks/useEchoPrivateChannel";
import {
  orderCreated,
  orderStatusUpdated,
} from "../../../redux/slices/adminOrderSlice";

/**
 * Keeps the admin orders table in sync with Reverb.
 *
 * Subscribes to `private-admin.orders` because `echo.private("admin.orders")`
 * adds the "private-" prefix automatically.
 *
 * Notifications/toasts are owned by `useAdminOrderNotifications` (mounted in
 * AdminHeader), so this hook only updates the table data.
 */
export function useAdminOrdersRealtime() {
  const dispatch = useDispatch();

  useEchoPrivateChannel("admin.orders", {
    ".order.created": (payload) => {
      const order = payload?.order ?? payload;
      if (!order?.id) return;

      dispatch(orderCreated(order));
    },

    ".order.status.updated": (payload) => {
      const order = payload?.order ?? payload;
      if (!order?.id || !order.status) return;

      dispatch(
        orderStatusUpdated({
          orderId: Number(order.id),
          status: order.status,
        }),
      );
    },
  });
}

export default useAdminOrdersRealtime;
