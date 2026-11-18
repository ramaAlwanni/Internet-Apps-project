import { useDispatch } from "react-redux";
import toast from "react-hot-toast";

import { useEchoPrivateChannel } from "../../../hooks/useEchoPrivateChannel";
import { notificationReceived } from "../../../redux/slices/notificationSlice";
import { orderStatusUpdated } from "../../../redux/slices/orderSlice";
import { formatStatus } from "../utils/formatNotification";

/**
 * Listens for status changes of the signed-in customer's orders.
 *
 * The backend broadcasts every status change to `user.{userId}`, so the
 * customer is notified on any page, not only while an order modal is open.
 *
 * @param {number|string|null|undefined} userId
 */
export function useCustomerOrderNotifications(userId) {
    const dispatch = useDispatch();

    useEchoPrivateChannel(userId ? `user.${userId}` : null, {
        ".order.status.updated": (payload) => {
            const order = payload?.order ?? payload;
            if (!order?.id || !order.status) return;

            const status = formatStatus(order.status);

            // Keep the orders list (and the open modal) in sync too.
            dispatch(
                orderStatusUpdated({
                    orderId: Number(order.id),
                    status: order.status,
                }),
            );

            dispatch(
                notificationReceived({
                    id: `customer-order-status-${order.id}-${order.status}-${
                        order.updated_at ?? Date.now()
                    }`,
                    audience: "customer",
                    kind: "order-status",
                    orderId: Number(order.id),
                    status: order.status,
                    title: `Order #ORD-${order.id} is ${status}`,
                    message: `Your order #ORD-${order.id} status changed to ${status}.`,
                    createdAt: order.updated_at ?? new Date().toISOString(),
                }),
            );

            toast.success(`Order #ORD-${order.id} is now ${status}`);
        },
    });
}

export default useCustomerOrderNotifications;
