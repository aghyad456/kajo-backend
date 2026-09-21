<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\StoreOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    // ---------- Public ----------

    public function index(Request $request)
    {
        $perPage = (int) ($request->query('per_page', 12));

        $stores = Store::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $stores->getCollection()->map(fn($store) => $this->serializeStore($store));

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $stores->currentPage(),
                'last_page' => $stores->lastPage(),
                'per_page' => $stores->perPage(),
                'total' => $stores->total(),
            ],
        ]);
    }

    public function show(Store $store)
    {
        return response()->json([
            'store' => $this->serializeStore($store),
        ]);
    }

    public function publicProducts(Store $store)
    {
        $products = $store->products()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get()
            ->map(fn($product) => $this->serializeProduct($product));

        return response()->json([
            'products' => $products,
        ]);
    }

    // ---------- Store owner ----------

    public function setup(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 3) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'avatar' => ['nullable', 'image', 'max:5120'],
        ]);

        $store = Store::where('user_id', $user->id)->first();
        $avatarPath = $store?->avatar_path;

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('stores', 'public');
        }

        $store = Store::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $validated['name'],
                'specialty' => $validated['specialty'] ?? null,
                'location' => $validated['location'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'description' => $validated['description'] ?? null,
                'avatar_path' => $avatarPath,
                'is_active' => true,
            ]
        );

        if (!$user->has_setup) {
            $user->has_setup = true;
            $user->save();
        }

        return response()->json([
            'message' => 'تم إعداد المتجر بنجاح',
            'store' => $this->serializeStore($store),
            'user' => $user,
        ]);
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 3) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $store = Store::where('user_id', $user->id)->first();

        if (!$store) {
            return response()->json([
                'profile' => null,
                'statistics' => [
                    'products' => 0,
                    'active_products' => 0,
                    'out_of_stock' => 0,
                    'pending_orders' => 0,
                ],
                'products' => [],
                'orders' => [],
            ]);
        }

        $products = $store->products()
            ->orderByDesc('id')
            ->get()
            ->map(fn($product) => $this->serializeProduct($product));

        $orders = $store->orders()
            ->with(['user:id,name,email', 'product:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($order) => $this->serializeOrder($order));

        return response()->json([
            'profile' => $this->serializeStore($store),
            'statistics' => [
                'products' => $store->products()->count(),
                'active_products' => $store->products()->where('is_active', true)->count(),
                'out_of_stock' => $store->products()->where('quantity', '<=', 0)->count(),
                'pending_orders' => $store->orders()->where('status', 'pending')->count(),
            ],
            'products' => $products,
            'orders' => $orders,
        ]);
    }

    public function products(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 3) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $store = Store::where('user_id', $user->id)->first();

        if (!$store) {
            return response()->json(['products' => []]);
        }

        $products = $store->products()
            ->orderByDesc('id')
            ->get()
            ->map(fn($product) => $this->serializeProduct($product));

        return response()->json(['products' => $products]);
    }

    public function storeProduct(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 3) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $store = Store::where('user_id', $user->id)->first();

        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'user_price' => ['required', 'numeric', 'min:0'],
            'store_price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:0'],
            'expiry' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('store_products', 'public');
        }

        $product = StoreProduct::create([
            'store_id' => $store->id,
            'name' => $validated['name'],
            'category' => $validated['category'] ?? null,
            'user_price' => $validated['user_price'],
            'store_price' => $validated['store_price'] ?? null,
            'quantity' => $validated['quantity'],
            'expiry' => $validated['expiry'] ?? null,
            'description' => $validated['description'] ?? null,
            'image_path' => $imagePath,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'تمت إضافة المنتج',
            'product' => $this->serializeProduct($product),
        ], 201);
    }

    public function deleteProduct(Request $request, $id)
    {
        $user = $request->user();

        if ((int) $user->role !== 3) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $store = Store::where('user_id', $user->id)->first();

        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $product = StoreProduct::where('store_id', $store->id)->where('id', $id)->first();

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $product->delete();

        return response()->json(['message' => 'تم حذف المنتج']);
    }

    // ---------- Orders ----------

    // POST /api/store/products/{product}/order
    public function createOrder(Request $request, $product)
    {
        $user = $request->user();

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $product = StoreProduct::with('store')->find($product);

        if (!$product || !$product->store || !$product->is_active) {
            return response()->json(['message' => 'المنتج غير موجود'], 404);
        }

        if ($product->quantity <= 0) {
            return response()->json(['message' => 'المنتج غير متوفر حالياً'], 422);
        }

        $order = StoreOrder::create([
            'store_id' => $product->store_id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'quantity' => $validated['quantity'],
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        $order->load(['user:id,name,email', 'product:id,name', 'store:id,name']);

        return response()->json([
            'message' => 'تم إرسال الطلب بنجاح',
            'order' => $this->serializeOrder($order),
        ], 201);
    }

    // GET /api/store/orders
    public function orders(Request $request)
    {
        $user = $request->user();

        if ((int) $user->role !== 3) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $store = Store::where('user_id', $user->id)->first();

        if (!$store) {
            return response()->json(['orders' => []]);
        }

        $orders = $store->orders()
            ->with(['user:id,name,email', 'product:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($order) => $this->serializeOrder($order));

        return response()->json([
            'orders' => $orders,
        ]);
    }

    // PATCH /api/store/orders/{id}
    public function updateOrderStatus(Request $request, $id)
    {
        $user = $request->user();

        if ((int) $user->role !== 3) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ]);

        $store = Store::where('user_id', $user->id)->first();

        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        return DB::transaction(function () use ($store, $id, $validated) {
            $order = StoreOrder::where('store_id', $store->id)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return response()->json(['message' => 'الطلب غير موجود'], 404);
            }

            if ($order->status !== 'pending') {
                return response()->json(['message' => 'تمت معالجة الطلب مسبقاً'], 422);
            }

            $product = StoreProduct::where('store_id', $store->id)
                ->where('id', $order->product_id)
                ->lockForUpdate()
                ->first();

            if (!$product) {
                return response()->json(['message' => 'المنتج غير موجود'], 404);
            }

            if ($validated['status'] === 'approved') {
                if ($product->quantity < $order->quantity) {
                    return response()->json(['message' => 'الكمية غير كافية لقبول الطلب'], 422);
                }

                $product->quantity = $product->quantity - $order->quantity;
                $product->save();
            }

            $order->status = $validated['status'];
            $order->save();

            return response()->json([
                'message' => $validated['status'] === 'approved'
                    ? 'تم قبول الطلب وتحديث الكمية'
                    : 'تم رفض الطلب',
            ]);
        });
    }

    private function serializeStore(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'specialty' => $store->specialty,
            'location' => $store->location,
            'phone' => $store->phone,
            'description' => $store->description,
            'rating' => (float) $store->rating,
            'avatar_url' => $store->avatar_url,
            'avatar_path' => $store->avatar_path,
            'is_active' => (bool) $store->is_active,
        ];
    }

    private function serializeProduct(StoreProduct $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'category' => $product->category,
            'user_price' => $product->user_price,
            'store_price' => $product->store_price,
            'quantity' => $product->quantity,
            'expiry' => $product->expiry?->format('Y-m-d'),
            'description' => $product->description,
            'image_url' => $product->image_url,
            'image_path' => $product->image_path,
            'is_active' => (bool) $product->is_active,
        ];
    }

    private function serializeOrder(StoreOrder $order): array
    {
        return [
            'id' => $order->id,
            'store_id' => $order->store_id,
            'product_id' => $order->product_id,
            'product_name' => $order->product?->name ?? '—',
            'user_id' => $order->user_id,
            'customer_name' => $order->user?->name ?? '—',
            'customer_email' => $order->user?->email ?? '—',
            'quantity' => $order->quantity,
            'status' => $order->status,
            'notes' => $order->notes,
            'created_at' => optional($order->created_at)->format('Y-m-d H:i'),
        ];
    }
}