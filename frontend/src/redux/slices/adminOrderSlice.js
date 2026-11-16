import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import { getAdminOrdersApi } from "../../services/api"; 


export const fetchAdminOrders = createAsyncThunk(
    "adminOrders/fetchAdminOrders",
    async (_, { rejectWithValue }) => {
        try {
            const response = await getAdminOrdersApi();
     
            return response.data?.data || response.data || response;
        } catch (error) {
            return rejectWithValue(
                error.response?.data?.message || "Failed to fetch orders",
            );
        }
    },
);

const adminOrderSlice = createSlice({
    name: "adminOrders",
    initialState: {
        orders: [],
        loading: false,
        error: null,
    },
    reducers: {
        orderCreated: (state, action) => {
            const order = action.payload;
            if (!order?.id) return;

            const index = state.orders.findIndex(
                (existing) => Number(existing.id) === Number(order.id),
            );

            if (index >= 0) {
                state.orders[index] = { ...state.orders[index], ...order };
            } else {
                state.orders.unshift(order);
            }
        },
        orderStatusUpdated: (state, action) => {
            const { orderId, status } = action.payload ?? {};
            if (!orderId || !status) return;

            const order = state.orders.find(
                (existing) => Number(existing.id) === Number(orderId),
            );

            if (order) {
                order.status = status;
            }
        },
    },
    extraReducers: (builder) => {
        builder
            .addCase(fetchAdminOrders.pending, (state) => {
                state.loading = true;
                state.error = null;
            })
            .addCase(fetchAdminOrders.fulfilled, (state, action) => {
                state.loading = false;
                state.orders = action.payload;
            })
            .addCase(fetchAdminOrders.rejected, (state, action) => {
                state.loading = false;
                state.error = action.payload;
            });
    },
});

export const { orderCreated, orderStatusUpdated } = adminOrderSlice.actions;
export default adminOrderSlice.reducer;
