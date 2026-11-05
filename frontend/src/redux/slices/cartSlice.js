import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import {
    addToCartApi,
    getCartApi,
    checkoutCartApi,
    removeFromCartApi,
    updateCartItemQuantityApi,
} from "../../services/api";

export const fetchCart = createAsyncThunk(
    "cart/fetchCart",
    async (_, thunkAPI) => {
        try {
            const data = await getCartApi();
            return data;
        } catch (error) {
            const message =
                error.response?.data?.message ||
                error.message ||
                "Failed to fetch cart";
            return thunkAPI.rejectWithValue(message);
        }
    },
);
export const checkoutCart = createAsyncThunk(
    "cart/checkoutCart",
    async (_, thunkAPI) => {
        try {
            const data = await checkoutCartApi();
            return data;
        } catch (error) {
            const message =
                error.response?.data?.message ||
                error.message ||
                "Failed to create order";
            return thunkAPI.rejectWithValue(message);
        }
    },
);

export const addToCart = createAsyncThunk(
    "cart/addToCart",
    async (cartData, thunkAPI) => {
        try {
            const data = await addToCartApi(cartData);
            return data;
        } catch (error) {
            const message =
                error.response?.data?.message ||
                error.message ||
                "Failed to add product to cart";
            return thunkAPI.rejectWithValue(message);
        }
    },
);
export const removeFromCart = createAsyncThunk(
    "cart/removeFromCart",
    async (productId, thunkAPI) => {
        try {
            const data = await removeFromCartApi(productId);
            return data;
        } catch (error) {
            const message =
                error.response?.data?.message ||
                error.message ||
                "Failed to remove product from cart";
            return thunkAPI.rejectWithValue(message);
        }
    },
);
export const updateCartQuantity = createAsyncThunk(
    "cart/updateCartQuantity",
    async ({ productId, quantity }, thunkAPI) => {
        try {
            const data = await updateCartItemQuantityApi(productId, quantity);
            return data;
        } catch (error) {
            const message =
                error.response?.data?.message ||
                error.message ||
                "Failed to update quantity";
            return thunkAPI.rejectWithValue(message);
        }
    },
);

const cartSlice = createSlice({
    name: "cart",
    initialState: {
        cartItems: [],
        latestOrder: null,
        isLoading: false,
        isError: false,
        message: "",
    },
    reducers: {},
    extraReducers: (builder) => {
        builder

            .addCase(fetchCart.pending, (state) => {
                state.isLoading = true;
                state.isError = false;
            })
            .addCase(fetchCart.fulfilled, (state, action) => {
                state.isLoading = false;

                state.cartItems = action.payload.data?.items || [];
            })
            .addCase(fetchCart.rejected, (state, action) => {
                state.isLoading = false;
                state.isError = true;
                state.message = action.payload;
            })

            .addCase(addToCart.pending, (state) => {
                state.isLoading = true;
            })
            .addCase(addToCart.fulfilled, (state) => {
                state.isLoading = false;
            })
            .addCase(addToCart.rejected, (state, action) => {
                state.isLoading = false;
                state.isError = true;
                state.message = action.payload;
            })
            .addCase(checkoutCart.pending, (state) => {
                state.isLoading = true;
                state.isError = false;
            })
            .addCase(checkoutCart.fulfilled, (state, action) => {
                state.isLoading = false;
                state.latestOrder = action.payload.data;
                state.cartItems = [];
            })
            .addCase(checkoutCart.rejected, (state, action) => {
                state.isLoading = false;
                state.isError = true;
                state.message = action.payload;
            })
            .addCase(removeFromCart.fulfilled, (state, action) => {
                const removedProductId = action.meta.arg;
                state.cartItems = state.cartItems.filter(
                    (item) => item.product_id !== removedProductId,
                );
            })
            .addCase(updateCartQuantity.fulfilled, (state, action) => {
                const updatedItem = action.payload.data;
                if (updatedItem && updatedItem.items) {
                    state.cartItems = updatedItem.items;
                }
            });
    },
});

export default cartSlice.reducer;
