<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BluetoothPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BluetoothPrinterController extends Controller
{
    /**
     * List all printers for the current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $printers = BluetoothPrinter::query()
            ->when($request->outlet_id, fn($q) => $q->where('outlet_id', $request->outlet_id))
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $printers]);
    }

    /**
     * Store a new printer.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'mac_address' => 'nullable|string|max:255',
            'outlet_id'   => 'nullable|uuid|exists:outlets,id',
            'is_default'  => 'boolean',
            'type'        => 'sometimes|string|in:cashier,kitchen,both',
        ]);

        $tenantId = Auth::user()->tenant_id;

        DB::transaction(function () use ($validated, $tenantId) {
            // If this will be the default, un-default all others first
            if (!empty($validated['is_default'])) {
                BluetoothPrinter::where('tenant_id', $tenantId)->update(['is_default' => false]);
            }

            $this->printer = BluetoothPrinter::create(array_merge($validated, [
                'tenant_id' => $tenantId,
            ]));
        });

        return response()->json(['success' => true, 'data' => $this->printer ?? BluetoothPrinter::latest()->first()], 201);
    }

    /**
     * Update a printer.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $printer = BluetoothPrinter::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'mac_address' => 'nullable|string|max:255',
            'outlet_id'   => 'nullable|uuid|exists:outlets,id',
            'type'        => 'sometimes|string|in:cashier,kitchen,both',
        ]);

        $printer->update($validated);

        return response()->json(['success' => true, 'data' => $printer->fresh()]);
    }

    /**
     * Delete a printer.
     */
    public function destroy(string $id): JsonResponse
    {
        $printer = BluetoothPrinter::findOrFail($id);
        $printer->delete();

        return response()->json(['success' => true, 'message' => 'Printer dihapus.']);
    }

    /**
     * Set a printer as the default for the tenant.
     */
    public function setDefault(string $id): JsonResponse
    {
        $printer = BluetoothPrinter::findOrFail($id);
        $tenantId = Auth::user()->tenant_id;

        DB::transaction(function () use ($printer, $tenantId) {
            BluetoothPrinter::where('tenant_id', $tenantId)->update(['is_default' => false]);
            $printer->update(['is_default' => true]);
        });

        return response()->json(['success' => true, 'data' => $printer->fresh()]);
    }
}
