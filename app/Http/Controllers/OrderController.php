<?php

namespace App\Http\Controllers;

use App\Domain\OrderService;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(CreateOrderRequest $request, OrderService $orders): JsonResponse
    {
        $order = $orders->create($request->validated('sku'));

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): OrderResource
    {
        $order = Order::query()->with('fulfillment')->find($id);

        if ($order === null) {
            throw (new ModelNotFoundException)->setModel(Order::class, [$id]);
        }

        return new OrderResource($order);
    }
}
