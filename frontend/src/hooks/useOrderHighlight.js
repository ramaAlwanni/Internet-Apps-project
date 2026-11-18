import { useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";

import {
    clearHighlightOrder,
    selectHighlightedOrderId,
} from "../redux/slices/notificationSlice";

const HIGHLIGHT_DURATION_MS = 6000;

/**
 * Highlights (and scrolls to) the order referenced by the last clicked
 * notification.
 *
 * @param {Array<{id: number|string}>} orders The currently rendered orders.
 * @returns {{ highlightedOrderId: string|null, isHighlighted: (id: number|string) => boolean }}
 */
export function useOrderHighlight(orders) {
    const dispatch = useDispatch();
    const highlightedOrderId = useSelector(selectHighlightedOrderId);

    const containsHighlightedOrder =
        !!highlightedOrderId &&
        Array.isArray(orders) &&
        orders.some(
            (order) => String(order.id) === String(highlightedOrderId),
        );

    useEffect(() => {
        if (!highlightedOrderId || !containsHighlightedOrder) {
            return undefined;
        }

        // Wait a frame so the list has actually painted the highlighted row.
        const scrollTimer = window.setTimeout(() => {
            const element = document.querySelector(
                `[data-order-id="${highlightedOrderId}"]`,
            );
            element?.scrollIntoView({ behavior: "smooth", block: "center" });
        }, 120);

        const clearTimer = window.setTimeout(() => {
            dispatch(clearHighlightOrder());
        }, HIGHLIGHT_DURATION_MS);

        return () => {
            window.clearTimeout(scrollTimer);
            window.clearTimeout(clearTimer);
        };
    }, [highlightedOrderId, containsHighlightedOrder, dispatch]);

    const isHighlighted = (orderId) =>
        !!highlightedOrderId &&
        String(orderId) === String(highlightedOrderId);

    return { highlightedOrderId, isHighlighted };
}

export default useOrderHighlight;
