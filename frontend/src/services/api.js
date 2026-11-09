import axios from "axios";

const API = axios.create({
    // baseURL: "http://localhost:8080/api",
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

export const addProductApi = async (productData) => {
    const formData = new FormData();
    formData.append("name", productData.name);
    formData.append("description", productData.description);
    formData.append("price", productData.price);
    formData.append("stock", productData.stock || 10);
    formData.append("category_id", productData.category_id);
    if (productData.photo) {
        formData.append("photo", productData.photo);
    }

    const response = await API.post("/v1/products", formData, {
        headers: {
            "Content-Type": "multipart/form-data",
        },
    });
    return response.data;
};

export const addCategoryApi = async (categoryData) => {
    const response = await API.post("/v1/products/categories", categoryData);
    return response.data;
};

export const addToCartApi = async (cartData) => {
    const response = await API.post("/v1/orders/cart/items", {
        product_id: cartData.product_id,
        quantity: cartData.quantity,
    });
    return response.data;
};

export const getCartApi = async () => {
    const response = await API.get("/v1/orders/cart");
    return response.data;
};

export const checkoutCartApi = async () => {
    const response = await API.post("/v1/orders/cart/checkout");
    return response.data;
};

export const removeFromCartApi = async (productId) => {
    const response = await API.delete(`/v1/orders/cart/items/${productId}`);
    return response.data;
};

export const updateCartItemQuantityApi = async (productId, quantity) => {
    const response = await API.put(`/v1/orders/cart/items/${productId}`, {
        quantity: quantity,
    });
    return response.data;
};

export const getMyOrdersApi = async () => {
    const response = await API.get("/v1/orders");
    return response.data;
};

export const getAdminOrdersApi = async () => {
    const response = await API.get("/v1/orders/admin");
    return response.data;
};
export const updateOrderStatusApi = async (orderId, newStatus) => {
    const response = await API.put(`/v1/orders/${orderId}/status`, {
        status: newStatus,
    });
    return response.data;
};
export const signoutApi = async () => {
    const response = await API.post("/sign-out");
    return response.data;
};
