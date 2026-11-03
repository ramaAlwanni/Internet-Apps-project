import { BrowserRouter as Router, Routes, Route } from "react-router-dom";
import LoginPage from "./features/auth/pages/LoginPage";
import HomePage from "./features/homePage/pages/HomePage";
import CartPage from "./features/cart/pages/CartPage";
import OrdersPage from "./features/orders/pages/OrdersPage";
import AdminOrdersPage from "./features/adminOrders/pages/AdminOrdersPage";
import AdminProducts from "./features/adminProducts/pages/AdminProductsPage";

function App() {
    return (
        <Router>
            <Routes>
                <Route path="/" element={<LoginPage />} />

                <Route path="/home" element={<HomePage />} />
                <Route path="/cart" element={<CartPage />} />
                <Route path="/orders" element={<OrdersPage />} />

                <Route path="/admin/products" element={<AdminProducts />} />
                <Route path="/admin/orders" element={<AdminOrdersPage />} />
            </Routes>
        </Router>
    );
}

export default App;
