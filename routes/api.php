<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BluetoothPrinterController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\IngredientController;
use App\Http\Controllers\Api\V1\KitchenOrderController;
use App\Http\Controllers\Api\V1\ModifierController;
use App\Http\Controllers\Api\V1\OutletController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TableController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\UserManagementController;
use App\Http\Controllers\Api\V1\SuperAdminController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\SelfOrderController;
use App\Http\Controllers\Api\V1\TableQrController;
use App\Http\Controllers\Api\V1\PaymentSettingController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - V1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    Route::get('/app-version/latest', [\App\Http\Controllers\Api\V1\AppVersionController::class, 'latest']);

    // ─── Public Auth Routes ───────────────────────────────────────────────────
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login',    [AuthController::class, 'login']);
    Route::post('/auth/refresh',  [AuthController::class, 'refresh']);
    Route::post('/auth/otp/send',   [AuthController::class, 'sendOtp']);
    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    // ─── Payment Webhooks ────────────────────────────────────────────────────
    Route::post('/webhooks/midtrans', [WebhookController::class, 'midtrans']);
    Route::post('/webhooks/pakasir',  [WebhookController::class, 'pakasir']);


    // ─── QR Self Order Public API (rate limited, no auth) ────────────────────
    Route::prefix('public')->middleware('throttle:30,1')->group(function () {
        Route::get('/table/{qr_token}',            [SelfOrderController::class, 'resolveTable']);
        Route::get('/menu/{outlet_id}',            [SelfOrderController::class, 'menu']);
        Route::post('/self-order/session',         [SelfOrderController::class, 'createSession']);
        Route::post('/self-order',                 [SelfOrderController::class, 'submitOrder']);
        Route::get('/self-order/{sessionToken}/status', [SelfOrderController::class, 'orderStatus']);
        Route::get('/self-order/{sessionToken}/receipt', [SelfOrderController::class, 'publicReceipt']);
    });

    // ─── Subscription Plans (public) ──────────────────────────────────────────
    Route::get('/plans', [SubscriptionController::class, 'plans']);

    // ─── System Settings (public) ─────────────────────────────────────────────
    Route::get('/system-settings', [\App\Http\Controllers\Api\V1\SystemSettingController::class, 'index']);

    // ─── Super Admin Routes (no tenant/subscription middleware) ───────────────
    Route::middleware(['auth:api', 'role:super_admin'])->prefix('super-admin')->group(function () {
        Route::get('/stats',                    [SuperAdminController::class, 'stats']);

        Route::get('/tenants',                  [SuperAdminController::class, 'tenants']);
        Route::get('/tenants/{id}',             [SuperAdminController::class, 'showTenant']);
        Route::put('/tenants/{id}',             [SuperAdminController::class, 'updateTenant']);
        Route::delete('/tenants/{id}',          [SuperAdminController::class, 'destroyTenant']);

        Route::get('/users',                    [SuperAdminController::class, 'users']);

        Route::get('/subscriptions',            [SuperAdminController::class, 'subscriptions']);
        Route::put('/subscriptions/{id}',       [SuperAdminController::class, 'updateSubscription']);

        Route::get('/plans',                    [SuperAdminController::class, 'plans']);
        Route::post('/plans',                   [SuperAdminController::class, 'storePlan']);
        Route::put('/plans/{id}',               [SuperAdminController::class, 'updatePlan']);
        Route::delete('/plans/{id}',            [SuperAdminController::class, 'destroyPlan']);

        // ── Orders / Revenue Tracking ─────────────────────────────────────────
        Route::get('/orders',                   [SuperAdminController::class, 'orders']);
        Route::get('/orders/{id}',              [SuperAdminController::class, 'showOrder']);

        // ── bayar.gg Payment Statistics ───────────────────────────────────────
        Route::get('/payment-statistics',       [SuperAdminController::class, 'paymentStatistics']);

        // ── Payment Settings ──────────────────────────────────────────────────
        Route::get('/payment-settings',         [PaymentSettingController::class, 'getGlobalSettings']);
        Route::put('/payment-settings',         [PaymentSettingController::class, 'updateGlobalSettings']);

        // ── App Version Management ────────────────────────────────────────────
        Route::get('/app-versions',             [\App\Http\Controllers\Api\V1\AppVersionController::class, 'index']);
        Route::post('/app-versions',            [\App\Http\Controllers\Api\V1\AppVersionController::class, 'store']);
        Route::delete('/app-versions/{id}',     [\App\Http\Controllers\Api\V1\AppVersionController::class, 'destroy']);

        // ── System Settings Management ────────────────────────────────────────
        Route::get('/system-settings',          [\App\Http\Controllers\Api\V1\SystemSettingController::class, 'adminIndex']);
        Route::put('/system-settings',          [\App\Http\Controllers\Api\V1\SystemSettingController::class, 'update']);

        // ── Discount Management ───────────────────────────────────────────────
        Route::get('/discounts',                [SuperAdminController::class, 'indexDiscounts']);
        Route::post('/discounts',               [SuperAdminController::class, 'storeDiscount']);
        Route::get('/discounts/{id}',           [SuperAdminController::class, 'showDiscount']);
        Route::put('/discounts/{id}',           [SuperAdminController::class, 'updateDiscount']);
        Route::delete('/discounts/{id}',        [SuperAdminController::class, 'destroyDiscount']);

        // ── Product Templates ─────────────────────────────────────────────────
        Route::get('/templates',                [SuperAdminController::class, 'templates']);
        Route::post('/templates',               [SuperAdminController::class, 'storeTemplate']);
        Route::get('/templates/{id}',           [SuperAdminController::class, 'showTemplate']);
        Route::put('/templates/{id}',           [SuperAdminController::class, 'updateTemplate']);
        Route::delete('/templates/{id}',        [SuperAdminController::class, 'destroyTemplate']);
    });

    // ─── Shared Auth Routes (works for all authenticated users incl. super_admin) ─
    Route::middleware(['auth:api'])->prefix('auth')->group(function () {
        Route::post('/logout',  [AuthController::class, 'logout']);
        Route::get('/me',       [AuthController::class, 'me']);
    });

    // ─── Subscription Viewing & Purchase (no subscription middleware — owners must see even if expired) ───
    Route::middleware(['auth:api', 'tenant'])->group(function () {
        // current() is accessible by ALL authenticated tenant roles (cashier needs it for POS feature gating)
        Route::get('/subscriptions/current', [SubscriptionController::class, 'current']);

        // Management routes restricted to owner / super_admin only
        Route::middleware('role:super_admin,owner')->group(function () {
            Route::get('/subscriptions/history',   [SubscriptionController::class, 'history']);
            Route::get('/subscriptions/usage',     [SubscriptionController::class, 'usage']);
            Route::post('/subscriptions/subscribe',[SubscriptionController::class, 'subscribe']);
            Route::post('/subscriptions/validate-discount', [SubscriptionController::class, 'validateDiscount']);
            Route::get('/subscriptions/check-payment/{invoice}', [SubscriptionController::class, 'checkPayment']);
        });
    });

    // ─── Protected Routes ─────────────────────────────────────────────────────
    Route::middleware(['auth:api', 'tenant', 'subscription'])->group(function () {

        // ── Outlets (Read: all roles, Write: Owner / Admin only) ─────────────
        Route::apiResource('outlets', OutletController::class)->only(['index', 'show']);
        Route::middleware('role:super_admin,owner,admin,cashier')->group(function () {
            Route::apiResource('outlets', OutletController::class)->except(['index', 'show']);
        });

        // ── User Management (Owner / Admin) ───────────────────────────────────
        Route::middleware('role:super_admin,owner,admin,cashier')->prefix('users')->group(function () {
            Route::get('/',           [UserManagementController::class, 'index']);
            Route::post('/',          [UserManagementController::class, 'store'])->middleware('plan.limit:users,max_users');
            Route::get('/{id}',       [UserManagementController::class, 'show']);
            Route::put('/{id}',       [UserManagementController::class, 'update']);
            Route::delete('/{id}',    [UserManagementController::class, 'destroy']);
        });

        // ── Products (Admin+) ─────────────────────────────────────────────────
        Route::middleware('role:super_admin,owner,admin')->group(function () {
            Route::post('/products',    [ProductController::class, 'store'])->middleware('plan.limit:products,max_products');
            Route::put('/products/{product}',     [ProductController::class, 'update']);
            Route::delete('/products/{product}',  [ProductController::class, 'destroy']);
        });
        Route::apiResource('products', ProductController::class)->only(['index', 'show']);

        // ── Product → Modifier Group assignment ───────────────────────────────
        Route::middleware('role:super_admin,owner,admin')->group(function () {
            Route::post('/products/{id}/modifier-groups', function (\Illuminate\Http\Request $req, $id) {
                $product = \App\Models\Product::findOrFail($id);
                $product->modifierGroups()->syncWithoutDetaching($req->input('modifier_group_ids', []));
                return response()->json(['success' => true, 'data' => $product->load('modifierGroups.modifiers')]);
            });
            Route::delete('/products/{id}/modifier-groups/{groupId}', function ($id, $groupId) {
                $product = \App\Models\Product::findOrFail($id);
                $product->modifierGroups()->detach($groupId);
                return response()->json(['success' => true]);
            });
        });

        // ── Categories (Admin+) ───────────────────────────────────────────────
        Route::get('/categories',       [CategoryController::class, 'index']);
        Route::get('/categories/{category}',  [CategoryController::class, 'show']);
        Route::middleware('role:super_admin,owner,admin')->group(function () {
            Route::post('/categories',              [CategoryController::class, 'store'])->middleware('plan.limit:categories,max_categories');
            Route::put('/categories/{category}',    [CategoryController::class, 'update']);
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        });

        // ── Customers ─────────────────────────────────────────────────────────
        Route::apiResource('customers', CustomerController::class);

        // ── Modifier Groups & Modifiers (Admin+) ──────────────────────────────
        Route::middleware(['role:super_admin,owner,admin', 'feature:modifiers'])->group(function () {
            Route::get('/modifier-groups',                              [ModifierController::class, 'indexGroups']);
            Route::post('/modifier-groups',                             [ModifierController::class, 'storeGroup'])->middleware('plan.limit:modifier_groups,max_modifiers');
            Route::get('/modifier-groups/{id}',                         [ModifierController::class, 'showGroup']);
            Route::put('/modifier-groups/{id}',                         [ModifierController::class, 'updateGroup']);
            Route::delete('/modifier-groups/{id}',                      [ModifierController::class, 'destroyGroup']);
            Route::post('/modifier-groups/{groupId}/modifiers',         [ModifierController::class, 'storeModifier']);
            Route::put('/modifier-groups/{groupId}/modifiers/{id}',     [ModifierController::class, 'updateModifier']);
            Route::delete('/modifier-groups/{groupId}/modifiers/{id}',  [ModifierController::class, 'destroyModifier']);
        });

        // ── Ingredients & Stock (Admin+) ──────────────────────────────────────
        Route::middleware(['role:super_admin,owner,admin', 'feature:inventory_basic'])->group(function () {
            Route::get('/ingredients/low-stock',    [IngredientController::class, 'lowStock']);
            Route::get('/ingredients',              [IngredientController::class, 'index']);
            Route::get('/ingredients/{ingredient}', [IngredientController::class, 'show']);
            Route::post('/ingredients',             [IngredientController::class, 'store'])->middleware('plan.limit:ingredients,max_ingredients');
            Route::put('/ingredients/{ingredient}', [IngredientController::class, 'update']);
            Route::delete('/ingredients/{ingredient}', [IngredientController::class, 'destroy']);
            Route::post('/ingredients/{id}/adjust', [IngredientController::class, 'adjustStock']);
        });

        // ── Recipes (Admin+) ──────────────────────────────────────────────────
        Route::middleware(['role:super_admin,owner,admin', 'feature:inventory_recipe'])->group(function () {
            Route::get('/products/{productId}/recipe',    [RecipeController::class, 'show']);
            Route::post('/products/{productId}/recipe',   [RecipeController::class, 'upsert']);
            Route::delete('/products/{productId}/recipe', [RecipeController::class, 'destroy']);

            // Supporting the frontend route format: /recipes/{id}
            Route::get('/recipes/{productId}',    [RecipeController::class, 'show']);
            Route::post('/recipes/{productId}',   [RecipeController::class, 'upsert']);
            Route::delete('/recipes/{productId}', [RecipeController::class, 'destroy']);
        });

        // ── Tables (Admin+, Cashier can read) ─────────────────────────────────
        Route::get('/tables',        [TableController::class, 'index']);
        Route::get('/tables/{id}',   [TableController::class, 'show']);
        Route::middleware('role:super_admin,owner,admin')->group(function () {
            Route::post('/tables',              [TableController::class, 'store']);
            Route::put('/tables/{id}',          [TableController::class, 'update']);
            Route::delete('/tables/{id}',       [TableController::class, 'destroy']);
            // QR Self Order management
            Route::post('/tables/{id}/qr/generate',  [TableQrController::class, 'generate']);
            Route::patch('/tables/{id}/qr/toggle',   [TableQrController::class, 'toggle']);
            Route::get('/tables/{id}/qr',            [TableQrController::class, 'show']);
        });
        Route::middleware('role:super_admin,owner,admin,cashier')->group(function () {
            Route::patch('/tables/{id}/status', [TableController::class, 'updateStatus']);
        });

        // ── Kitchen Orders (Kitchen staff + Admin) ────────────────────────────
        Route::middleware(['role:super_admin,owner,admin,kitchen,cashier', 'feature:kitchen_display'])->group(function () {
            Route::get('/kitchen-orders',              [KitchenOrderController::class, 'index']);
            Route::get('/kitchen-orders/{id}',         [KitchenOrderController::class, 'show']);
            Route::patch('/kitchen-orders/{id}/status',[KitchenOrderController::class, 'updateStatus']);
        });

        // ── Transactions (Cashier+) ───────────────────────────────────────────
        Route::get('/transactions',               [TransactionController::class, 'index']);
        Route::post('/transactions',              [TransactionController::class, 'store']);
        Route::get('/transactions/{id}',          [TransactionController::class, 'show']);
        Route::get('/transactions/{id}/receipt',  [TransactionController::class, 'receipt']);
        Route::post('/transactions/{id}/cancel',  [TransactionController::class, 'cancel']);
        Route::put('/transactions/{id}',          [TransactionController::class, 'update']);
        Route::middleware('role:super_admin,owner,admin')->group(function () {
            Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);
        });

        // ── Bluetooth Printers (Admin Operational, Owner View-only) ───────────
        Route::get('/bluetooth-printers', [BluetoothPrinterController::class, 'index']);
        Route::middleware('role:super_admin,admin,cashier')->group(function () {
            Route::post('/bluetooth-printers',                    [BluetoothPrinterController::class, 'store']);
            Route::put('/bluetooth-printers/{id}',               [BluetoothPrinterController::class, 'update']);
            Route::delete('/bluetooth-printers/{id}',            [BluetoothPrinterController::class, 'destroy']);
            Route::patch('/bluetooth-printers/{id}/set-default', [BluetoothPrinterController::class, 'setDefault']);
        });
        Route::middleware('role:owner')->group(function () {
            // Already covered by top-level index for read
        });

        // ── Shifts (Cashier+) ─────────────────────────────────────────────────
        Route::get('/shifts',                     [ShiftController::class, 'index']);
        Route::get('/shifts/current',             [ShiftController::class, 'current']);
        Route::get('/shifts/{id}',                [ShiftController::class, 'show']);
        Route::middleware('role:super_admin,owner,admin,cashier')->group(function () {
            Route::post('/shifts',                    [ShiftController::class, 'store']);
            Route::patch('/shifts/{id}/close',        [ShiftController::class, 'close']);
            Route::post('/shifts/{id}/cash-logs',     [ShiftController::class, 'addCashLog']);
        });

        // ── Reports ───────────────────────────────────────────────────────────
        Route::middleware(['role:super_admin,owner,admin', 'feature:advanced_reports'])->prefix('reports')->group(function () {
            Route::get('/daily',        [ReportController::class, 'daily']);
            Route::get('/monthly',      [ReportController::class, 'monthly']);
            Route::get('/top-products', [ReportController::class, 'topProducts']);
            Route::get('/export-csv',   [ReportController::class, 'exportCsv']);
            Route::get('/profit',       [ReportController::class, 'profit']);
            Route::get('/by-staff',     [ReportController::class, 'byStaff']);
            Route::get('/by-outlet',    [ReportController::class, 'byOutlet']);
            Route::get('/dead-stock',   [ReportController::class, 'deadStock']);
            Route::get('/income',       [ReportController::class, 'income']);
            Route::get('/expense',      [ReportController::class, 'expense']);
            Route::get('/profit-loss',  [ReportController::class, 'profitLossSummary']);
            
            Route::get('/income/export',      [ReportController::class, 'exportIncome']);
            Route::get('/expense/export',     [ReportController::class, 'exportExpense']);
            Route::get('/profit-loss/export', [ReportController::class, 'exportProfitLoss']);
        });

        // ── AI Assistant ──────────────────────────────────────────────────────
        Route::middleware('role:super_admin,owner,admin')->prefix('ai')->group(function () {
            Route::get('/context', [AiController::class, 'getContext']);
        });

        // ── Expenses (Admin+, Cashier can record) ──────────────────────────────
        Route::middleware('feature:expenses')->group(function () {
            Route::get('/expense-categories', [ExpenseController::class, 'indexCategories']);
            Route::middleware('role:super_admin,owner,admin')->group(function () {
                Route::post('/expense-categories', [ExpenseController::class, 'storeCategory']);
            });

            Route::get('/expenses', [ExpenseController::class, 'index']);
            Route::post('/expenses', [ExpenseController::class, 'store']);
            Route::middleware('role:super_admin,owner,admin')->group(function () {
                Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);
            });
        });

        Route::middleware(['role:super_admin,owner', 'feature:audit_log'])->group(function () {
            Route::get('/audit-logs', [AuditLogController::class, 'index']);
        });

        // ─── Help Desk / Chat System ──────────────────────────────────────────
        Route::get('/tickets',              [\App\Http\Controllers\Api\V1\TicketController::class, 'index']);
        Route::post('/tickets',             [\App\Http\Controllers\Api\V1\TicketController::class, 'store']);
        Route::get('/tickets/{id}',         [\App\Http\Controllers\Api\V1\TicketController::class, 'show']);
        Route::patch('/tickets/{id}/status', [\App\Http\Controllers\Api\V1\TicketController::class, 'updateStatus']);
        Route::post('/chat/messages',       [\App\Http\Controllers\Api\V1\ChatMessageController::class, 'store']);

        // ── Tenant Payment Settings ──────────────────────────────────────────
        Route::middleware('role:super_admin,owner')->prefix('settings')->group(function () {
            Route::get('/payment', [PaymentSettingController::class, 'getTenantSettings']);
            Route::put('/payment', [PaymentSettingController::class, 'updateTenantSettings']);
        });

        // ── Onboarding ────────────────────────────────────────────────────────
        Route::prefix('onboarding')->group(function () {
            Route::get('/templates',    [\App\Http\Controllers\Api\V1\OnboardingController::class, 'templates']);
            Route::post('/import',      [\App\Http\Controllers\Api\V1\OnboardingController::class, 'import']);
            Route::post('/complete',    [\App\Http\Controllers\Api\V1\OnboardingController::class, 'complete']);
        });
    });
});
