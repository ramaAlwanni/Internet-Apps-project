import { BrowserRouter as Router, Routes, Route } from "react-router-dom";
import LoginPage from "./features/auth/pages/LoginPage";
import HomePage from "./features/homePage/pages/HomePage";
import CartPage from "./features/cart/pages/CartPage";
import OrdersPage from "./features/orders/pages/OrdersPage";

function App() {
    return (
        <Router>
            <Routes>
                <Route path="/" element={<LoginPage />} />

                <Route path="/home" element={<HomePage />} />
                <Route path="/cart" element={<CartPage />} />
                <Route path="/orders" element={<OrdersPage />} />
            </Routes>
        </Router>
    );
}

export default App;
