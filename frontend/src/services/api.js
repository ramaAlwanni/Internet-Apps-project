import axios from 'axios';

const API = axios.create({
    baseURL: 'http://127.0.0.1:8000/api', // رابط الـ Backend كما ظهر في بوستمان
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// دالة تسجيل الحساب (تستقبل بيانات المستخدم وترسلها للـ backend)
export const registerApi = async (userData) => {
    const response = await API.post('/signup', userData);
    return response.data;
};

// أضيفي هذه الدالة بجانب registerApi في ملف src/services/api.js
export const loginApi = async (credentials) => {
    const response = await API.post('/signin', credentials);
    return response.data;
};