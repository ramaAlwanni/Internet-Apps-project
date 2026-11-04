<?php

namespace Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Order\Services\OrderService;
use Throwable;
use Modules\Order\Http\Requests\UpdateOrderStatusRequest;
use Modules\Order\Models\Order;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {
    }

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully',
            'data' => $this->orderService->getUserOrders(
                $request->user()->id
            ),
        ]);
    }

    public function adminIndex()
    {
        return response()->json([
            'success' => true,
            'message' => 'All orders retrieved successfully',
            'data' => $this->orderService->getAllOrders(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        try {
            $order = $this->orderService->createOrder(
                $request->user()->id,
                $validated['items']
            );

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
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

    public function show(Request $request, int $order)
    {
        $orderData = $this->orderService->getOrderForUser(
            $order,
            $request->user()->id,
            $request->user()->hasRole('admin')
        );

        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully',
            'data' => $orderData,
        ]);
    }

    public function adminShow(int $order)
    {
        $orderData = $this->orderService->getOrderForUser(
            $order,
            0,
            true
        );

        return response()->json([
            'success' => true,
            'message' => 'Order details retrieved successfully',
            'data' => $orderData,
        ]);
    }

    public function updateStatus(
    UpdateOrderStatusRequest $request,
    Order $order
) {
    /*
     * يستخدم OrderPolicy::updateStatus()
     * ويتحقق من صلاحية change-order-status.
     */
    $this->authorize('updateStatus', $order);

    try {
        $updatedOrder = $this->orderService->updateStatus(
            $order->id,
            $request->validated('status')
        );

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $updatedOrder,
        ]);
    } catch (\Throwable $exception) {
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
            'data' => null,
        ], 409);
    }
}

}
