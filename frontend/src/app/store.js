import { configureStore } from "@reduxjs/toolkit";
import authReducer from "../features/auth/authSlice";
import categoryReducer from "../redux/slices/categorySlice";
import productReducer from "../redux/slices/productSlice";
import cartReducer from "../redux/slices/cartSlice";
import orderReducer from "../redux/slices/orderSlice";
export const store = configureStore({
    reducer: {
        auth: authReducer,
        category: categoryReducer,
        product: productReducer,
        cart: cartReducer,
        order: orderReducer,
    },
});
