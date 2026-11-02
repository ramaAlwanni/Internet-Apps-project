import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import { getProductsApi } from "../../services/api";

export const fetchProducts = createAsyncThunk(
    "product/fetchProducts",
    async ({ categoryId = "", page = 1 } = {}, thunkAPI) => {
        try {
            const data = await getProductsApi(categoryId, page);
            return data; 
        } catch (error) {
            const message =
                error.response?.data?.message ||
                error.message ||
                "Failed to fetch products";
            return thunkAPI.rejectWithValue(message);
        }
    },
);

const productSlice = createSlice({
    name: "product",
    initialState: {
        products: [],
        currentPage: 1,
        lastPage: 1,
        isLoading: false,
        isError: false,
        message: "",
    },
    reducers: {},
    extraReducers: (builder) => {
        builder
            .addCase(fetchProducts.pending, (state) => {
                state.isLoading = true;
                state.isError = false;
            })
            .addCase(fetchProducts.fulfilled, (state, action) => {
                state.isLoading = false;

                state.products = action.payload.data?.data || [];

                state.currentPage = action.payload.data?.current_page || 1;
                state.lastPage = action.payload.data?.last_page || 1;
            })
            .addCase(fetchProducts.rejected, (state, action) => {
                state.isLoading = false;
                state.isError = true;
                state.message = action.payload;
            });
    },
});

export default productSlice.reducer;
