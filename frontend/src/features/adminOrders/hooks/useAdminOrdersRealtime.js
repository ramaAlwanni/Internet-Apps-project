import { useDispatch } from "react-redux";
import toast from "react-hot-toast";

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
 */
export function useAdminOrdersRealtime() {
  const dispatch = useDispatch();

  useEchoPrivateChannel("admin.orders", {
    ".order.created": (payload) => {
      const order = payload?.order ?? payload;
      if (!order?.id) return;

      dispatch(orderCreated(order));
      toast.success(`New order #ORD-${order.id} received`);
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
