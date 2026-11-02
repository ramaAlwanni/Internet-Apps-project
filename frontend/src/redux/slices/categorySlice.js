import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import { getCategoriesApi } from "../../services/api";

export const fetchCategories = createAsyncThunk(
    "category/fetchCategories",
    async (_, thunkAPI) => {
        try {
            const data = await getCategoriesApi();
            return data; 
        } catch (error) {
            const message =
                error.response?.data?.message ||
                error.message ||
                "Failed to fetch categories";
            return thunkAPI.rejectWithValue(message);
        }
    }
);

const categorySlice = createSlice({
    name: "category",
    initialState: {
        categories: [],
        isLoading: false,
        isError: false,
        message: "",
    },
    reducers: {},
    extraReducers: (builder) => {
        builder
            .addCase(fetchCategories.pending, (state) => {
                state.isLoading = true;
                state.isError = false;
            })
            .addCase(fetchCategories.fulfilled, (state, action) => {
                state.isLoading = false;
       
                state.categories = action.payload.data || action.payload;
            })
            .addCase(fetchCategories.rejected, (state, action) => {
                state.isLoading = false;
                state.isError = true;
                state.message = action.payload;
            });
    },
});

export default categorySlice.reducer;