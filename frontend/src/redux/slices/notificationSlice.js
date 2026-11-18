import { createSlice } from "@reduxjs/toolkit";

/**
 * Session-only notifications.
 *
 * They are intentionally not persisted: every notification is derived from a
 * realtime broadcast, so replaying old ones after a reload would be misleading.
 * The slice only owns UI state (list, unread flags, highlighted order).
 */
const MAX_NOTIFICATIONS = 50;

const initialState = {
    items: [],
    highlightOrderId: null,
};

const notificationSlice = createSlice({
    name: "notifications",
    initialState,
    reducers: {
        notificationReceived: (state, action) => {
            const incoming = action.payload;
            if (!incoming?.id) return;

            // Reverb can redeliver the same event (reconnect, duplicate tab).
            if (state.items.some((item) => item.id === incoming.id)) return;

            state.items.unshift({
                read: false,
                createdAt: new Date().toISOString(),
                ...incoming,
            });

            if (state.items.length > MAX_NOTIFICATIONS) {
                state.items.length = MAX_NOTIFICATIONS;
            }
        },

        notificationRead: (state, action) => {
            const item = state.items.find(
                (notification) => notification.id === action.payload,
            );
            if (item) item.read = true;
        },

        allNotificationsRead: (state) => {
            state.items.forEach((item) => {
                item.read = true;
            });
        },

        clearNotifications: (state) => {
            state.items = [];
            state.highlightOrderId = null;
        },

        highlightOrder: (state, action) => {
            if (action.payload === null || action.payload === undefined) {
                state.highlightOrderId = null;
                return;
            }

            state.highlightOrderId = String(action.payload);
        },

        clearHighlightOrder: (state) => {
            state.highlightOrderId = null;
        },
    },

    extraReducers: (builder) => {
        builder.addCase("auth/logoutUser/fulfilled", (state) => {
            state.items = [];
            state.highlightOrderId = null;
        });
    },
});

export const {
    notificationReceived,
    notificationRead,
    allNotificationsRead,
    clearNotifications,
    highlightOrder,
    clearHighlightOrder,
} = notificationSlice.actions;

export const selectNotifications = (state) => state.notifications.items;

export const selectUnreadNotificationCount = (state) =>
    state.notifications.items.reduce(
        (count, item) => (item.read ? count : count + 1),
        0,
    );

export const selectHighlightedOrderId = (state) =>
    state.notifications.highlightOrderId;

export default notificationSlice.reducer;
