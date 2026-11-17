import { useDispatch } from "react-redux";
import toast from "react-hot-toast";

import { useEchoPrivateChannel } from "../../../hooks/useEchoPrivateChannel";
import { notificationReceived } from "../../../redux/slices/notificationSlice";
import { formatCurrency } from "../utils/formatNotification";

/**
 * Listens for brand new orders while any admin page is open.
 *
 * `echo.private("admin.orders")` authorizes against the `admin.orders`
 * channel in routes/channels.php.
 *
 * @param {boolean} enabled Only subscribe for admins.
 */
export function useAdminOrderNotifications(enabled = true) {
    const dispatch = useDispatch();

    useEchoPrivateChannel(enabled ? "admin.orders" : null, {
        ".order.created": (payload) => {
            const order = payload?.order ?? payload;
            if (!order?.id) return;

            const total = formatCurrency(order.total_amount);
            const customer = order.user?.name || "A customer";

            dispatch(
                notificationReceived({
                    id: `admin-order-created-${order.id}`,
                    audience: "admin",
                    kind: "order-created",
                    orderId: Number(order.id),
                    title: `New order #ORD-${order.id}`,
                    message: `${customer} placed an order for ${total}.`,
                    createdAt: order.created_at ?? new Date().toISOString(),
                }),
            );

            toast.success(`New order #ORD-${order.id} received`);
        },
    });
}

export default useAdminOrderNotifications;
