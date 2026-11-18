import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import { getMyOrdersApi } from "../../services/api";

export const fetchMyOrders = createAsyncThunk(
    "order/fetchMyOrders",
    async (page = 1, thunkAPI) => {
        try {
            const data = await getMyOrdersApi(page);
            return data; 
        } catch (error) {
            const message =
                error.response?.data?.message ||
                error.message ||
                "Failed to fetch orders";
            return thunkAPI.rejectWithValue(message);
        }
    }
);

const orderSlice = createSlice({
    name: "order",
    initialState: {
        orders: [],
        currentPage: 1,
        lastPage: 1,
        total: 0,
        isLoading: false,
        isError: false,
        message: "",
        statusById: {},
    },
    reducers: {
        orderStatusUpdated: (state, action) => {
            const { orderId, status } = action.payload ?? {};
            if (!orderId || !status) return;

            state.statusById[String(orderId)] = status;

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
            .addCase(fetchMyOrders.pending, (state) => {
                state.isLoading = true;
                state.isError = false;
            })
            .addCase(fetchMyOrders.fulfilled, (state, action) => {
                state.isLoading = false;
                const pagination = action.payload.data;
                state.orders = Array.isArray(pagination)
                    ? pagination
                    : pagination?.data || [];
                state.currentPage = pagination?.current_page || 1;
                state.lastPage = pagination?.last_page || 1;
                state.total = pagination?.total || state.orders.length;
            })
            .addCase(fetchMyOrders.rejected, (state, action) => {
                state.isLoading = false;
                state.isError = true;
                state.message = action.payload;
            });
    },
});

export const { orderStatusUpdated } = orderSlice.actions;
export default orderSlice.reducer;