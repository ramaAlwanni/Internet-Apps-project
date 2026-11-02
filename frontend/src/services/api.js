import axios from "axios";

const API = axios.create({
    baseURL: "http://127.0.0.1:8000/api",
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
});

API.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem("token");
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    },
);

export const registerApi = async (userData) => {
    const response = await API.post("/v1/users/sign-up", userData);
    return response.data;
};

export const loginApi = async (credentials) => {
    const response = await API.post("/v1/users/sign-in", credentials);
    return response.data;
};

export const getCategoriesApi = async () => {
    const response = await API.get("/v1/products/categories");
    return response.data;
};

export const getProductsApi = async (
    categoryId = "",
    page = 1,
    perPage = 4,
) => {
    let url = `/v1/products?page=${page}&per_page=${perPage}`;
    if (categoryId) {
        url += `&category_id=${categoryId}`;
    }
    const response = await API.get(url);
    return response.data;
};
