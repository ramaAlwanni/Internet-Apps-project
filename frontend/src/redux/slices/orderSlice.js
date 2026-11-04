import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import { getMyOrdersApi } from "../../services/api";

export const fetchMyOrders = createAsyncThunk(
    "order/fetchMyOrders",
    async (_, thunkAPI) => {
        try {
            const data = await getMyOrdersApi();
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
        isLoading: false,
        isError: false,
        message: "",
    },
    reducers: {},
    extraReducers: (builder) => {
        builder
            .addCase(fetchMyOrders.pending, (state) => {
                state.isLoading = true;
                state.isError = false;
            })
            .addCase(fetchMyOrders.fulfilled, (state, action) => {
                state.isLoading = false;
                state.orders = action.payload.data || [];
            })
            .addCase(fetchMyOrders.rejected, (state, action) => {
                state.isLoading = false;
                state.isError = true;
                state.message = action.payload;
            });
    },
});

export default orderSlice.reducer;