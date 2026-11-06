<?php

namespace Modules\Order\Http\Controllers;

use App\Contracts\Catalog\ProductCatalog;
use App\Http\Controllers\Controller;
use App\Rules\ProductExists;
use Illuminate\Http\Request;
use Modules\Order\Services\CartService;
use Modules\Order\Services\OrderService;
use Throwable;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderService $orderService,
        private readonly ProductCatalog $productCatalog
    ) {
    }

    /**
     * عرض سلة المستخدم.
     *
     * GET /api/v1/orders/cart
     */
    public function show(Request $request)
    {
        $cart = $this->cartService->getCart(
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Cart retrieved successfully',
            'data' => $cart,
        ]);
    }

    /**
     * إضافة منتج إلى السلة.
     *
     * POST /api/v1/orders/cart/items
     */
    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                new ProductExists($this->productCatalog),
            ],

            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        try {
            $cart = $this->cartService->addItem(
                $request->user()->id,
                (int) $validated['product_id'],
                (int) $validated['quantity']
            );

            return response()->json([
                'success' => true,
                'message' => 'Product added to cart successfully',
                'data' => $cart,
            ], 201);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], 409);
        }
    }

    /**
     * تعديل كمية منتج داخل السلة.
     *
     * PUT /api/v1/orders/cart/items/{product}
     */
    public function updateItem(
        Request $request,
        int $product
    ) {
        $validated = $request->validate([
            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        try {
            $cart = $this->cartService->updateItem(
                $request->user()->id,
                $product,
                (int) $validated['quantity']
            );

            return response()->json([
                'success' => true,
                'message' => 'Cart item updated successfully',
                'data' => $cart,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], 409);
        }
    }

    /**
     * حذف منتج من السلة.
     *
     * DELETE /api/v1/orders/cart/items/{product}
     */
    public function removeItem(
        Request $request,
        int $product
    ) {
        $cart = $this->cartService->removeItem(
            $request->user()->id,
            $product
        );

        return response()->json([
            'success' => true,
            'message' => 'Product removed from cart successfully',
            'data' => $cart,
        ]);
    }

    /**
     * تفريغ السلة.
     *
     * DELETE /api/v1/orders/cart
     */
    public function clear(Request $request)
    {
        $cart = $this->cartService->clearCart(
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared successfully',
            'data' => $cart,
        ]);
    }

    /**
     * تحويل السلة إلى طلب.
     *
     * POST /api/v1/orders/cart/checkout
     */
    public function checkout(Request $request)
    {
        try {
            $items = $this->cartService->getCheckoutItems(
                $request->user()->id
            );

            $order = $this->orderService->createOrder(
                $request->user()->id,
                $items
            );

            $this->cartService->clearCart(
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Order created from cart successfully',
                'data' => $order,
            ], 201);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], 409);
        }
    }
}
