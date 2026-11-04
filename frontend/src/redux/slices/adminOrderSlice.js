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
    reducers: {},
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

export default adminOrderSlice.reducer;
