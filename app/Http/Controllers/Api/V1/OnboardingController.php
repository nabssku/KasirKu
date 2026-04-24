<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OnboardingController extends Controller
{
    public function templates(): JsonResponse
    {
        $templates = ProductTemplate::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data'    => $templates,
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'template_id' => 'required|exists:product_templates,id',
        ]);

        $template = ProductTemplate::findOrFail($request->template_id);
        $user = auth()->user();
        $tenant = $user->tenant;

        return DB::transaction(function () use ($template, $tenant, $user) {
            // 1. Create Default Outlet
            $outlet = Outlet::create([
                'tenant_id'     => $tenant->id,
                'name'          => $tenant->name . ' - Outlet Pusat',
                'business_type' => strtolower($template->category_type),
                'is_active'     => true,
                'tax_rate'      => 11.00,
            ]);

            // 2. Create Employee Accounts (Option C: Same password as owner)
            $adminRole = Role::where('slug', 'admin')->first();
            $cashierRole = Role::where('slug', 'cashier')->first();
            
            // Format domain safely
            $domain = $tenant->domain ?? Str::slug($tenant->name);
            if (!str_contains($domain, '.')) $domain .= '.jagokasir.store';

            $adminUser = User::create([
                'tenant_id' => $tenant->id,
                'outlet_id' => $outlet->id,
                'name'      => 'Admin ' . $tenant->name,
                'email'     => 'admin@' . $domain,
                'password'  => $user->password, // Exact same hash
                'is_active' => true,
            ]);
            if ($adminRole) $adminUser->roles()->attach($adminRole->id);

            $cashierUser = User::create([
                'tenant_id' => $tenant->id,
                'outlet_id' => $outlet->id,
                'name'      => 'Kasir ' . $tenant->name,
                'email'     => 'kasir@' . $domain,
                'password'  => $user->password, // Exact same hash
                'is_active' => true,
            ]);
            if ($cashierRole) $cashierUser->roles()->attach($cashierRole->id);

            // 3. Import Template Data
            $data = $template->data;

            if (isset($data['categories'])) {
                foreach ($data['categories'] as $catData) {
                    $category = Category::create([
                        'tenant_id' => $tenant->id,
                        'name'      => $catData['name'],
                        'slug'      => Str::slug($catData['name']) . '-' . Str::random(5),
                    ]);

                    if (isset($catData['products'])) {
                        foreach ($catData['products'] as $prodData) {
                            $product = Product::create([
                                'tenant_id'   => $tenant->id,
                                'outlet_id'   => $outlet->id,
                                'category_id' => $category->id,
                                'name'        => $prodData['name'],
                                'price'       => $prodData['price'],
                                'stock'       => $prodData['stock'] ?? 10,
                                'is_active'   => true,
                            ]);

                            if (isset($prodData['modifier_groups'])) {
                                foreach ($prodData['modifier_groups'] as $groupData) {
                                    $group = ModifierGroup::create([
                                        'tenant_id' => $tenant->id,
                                        'name'      => $groupData['name'],
                                        'required'  => $groupData['required'] ?? false,
                                        'min_select'=> $groupData['min_select'] ?? 0,
                                        'max_select'=> $groupData['max_select'] ?? 1,
                                    ]);

                                    $product->modifierGroups()->attach($group->id);

                                    if (isset($groupData['modifiers'])) {
                                        foreach ($groupData['modifiers'] as $modData) {
                                            Modifier::create([
                                                'modifier_group_id' => $group->id,
                                                'name'              => $modData['name'],
                                                'price'             => $modData['price'] ?? 0,
                                                'is_available'      => true,
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Mark onboarding as complete
            $settings = $tenant->settings ?? [];
            $settings['onboarding_completed'] = true;
            $settings['onboarding_step'] = 'completed';
            $tenant->update(['settings' => $settings]);

            return response()->json([
                'success' => true,
                'message' => 'Template imported successfully with Outlet and Employee accounts.',
                'credentials' => [
                    'admin' => 'admin@' . $domain,
                    'cashier' => 'kasir@' . $domain,
                    'password_note' => 'Sama dengan password Owner'
                ]
            ]);
        });
    }

    public function complete(Request $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $settings = $tenant->settings ?? [];
        $settings['onboarding_completed'] = true;
        $settings['onboarding_step'] = 'completed';
        $tenant->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding completed.',
        ]);
    }
}
