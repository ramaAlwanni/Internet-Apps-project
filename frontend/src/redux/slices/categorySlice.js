import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import { getCategoriesApi, addCategoryApi } from "../../services/api";

export const fetchCategories = createAsyncThunk(
    "category/fetchCategories",
    async (_, thunkAPI) => {
        try {
            const data = await getCategoriesApi();
            return data;
        } catch (error) {
            return thunkAPI.rejectWithValue(
                error.response?.data?.message || error.message,
            );
        }
    },
);

export const addCategory = createAsyncThunk(
    "category/addCategory",
    async (categoryData, thunkAPI) => {
        try {
            const data = await addCategoryApi(categoryData);
            return data;
        } catch (error) {
            return thunkAPI.rejectWithValue(
                error.response?.data?.message || error.message,
            );
        }
    },
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
            })
            .addCase(fetchCategories.fulfilled, (state, action) => {
                state.isLoading = false;
                state.categories = action.payload.data || action.payload;
            })
            .addCase(addCategory.fulfilled, (state, action) => {
                const newCat = action.payload.data;
                if (newCat) {
                    state.categories.push(newCat);
                }
            });
    },
});

export default categorySlice.reducer;
