import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import { registerApi, loginApi } from "../../services/api";

export const registerUser = createAsyncThunk(
    "auth/registerUser",
    async (userData, thunkAPI) => {
        try {
            const data = await registerApi(userData);

            if (data.token || (data.data && data.data.token)) {
                const token = data.token || data.data.token;
                localStorage.setItem("token", token);
            }
            return data;
        } catch (error) {
            const message =
                error.response?.data?.message ||
                error.message ||
                "Registration failed";
            return thunkAPI.rejectWithValue(message);
        }
    },
);

export const loginUser = createAsyncThunk(
    "auth/loginUser",
    async (credentials, thunkAPI) => {
        try {
            const data = await loginApi(credentials);

            const token = data.token || (data.data && data.data.token);
            if (token) {
                localStorage.setItem("token", token);
            }
            return data;
        } catch (error) {
            const message =
                error.response?.data?.message ||
                error.message ||
                "Login failed";
            return thunkAPI.rejectWithValue(message);
        }
    },
);

const initialState = {
    user: null,
    token: localStorage.getItem("token") || null,
    isLoading: false,
    isError: false,
    message: "",
};

const authSlice = createSlice({
    name: "auth",
    initialState,
    reducers: {
        logout: (state) => {
            state.user = null;
            state.token = null;
            localStorage.removeItem("token");
        },
    },
    extraReducers: (builder) => {
        builder
            .addCase(registerUser.pending, (state) => {
                state.isLoading = true;
                state.isError = false;
            })
            .addCase(registerUser.fulfilled, (state, action) => {
                state.isLoading = false;
                state.user = action.payload.data?.user || action.payload.user;
                state.token =
                    action.payload.data?.token || action.payload.token;
            })
            .addCase(registerUser.rejected, (state, action) => {
                state.isLoading = false;
                state.isError = true;
                state.message = action.payload;
            })
            .addCase(loginUser.pending, (state) => {
                state.isLoading = true;
                state.isError = false;
            })
            .addCase(loginUser.fulfilled, (state, action) => {
                state.isLoading = false;
                state.user = action.payload.data?.user || action.payload.user;
                state.token =
                    action.payload.data?.token || action.payload.token;
            })
            .addCase(loginUser.rejected, (state, action) => {
                state.isLoading = false;
                state.isError = true;
                state.message = action.payload;
            });
    },
});

export const { logout } = authSlice.actions;
export default authSlice.reducer;
