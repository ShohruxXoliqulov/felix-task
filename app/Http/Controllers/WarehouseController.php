<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WarehouseController extends Controller
{
    public function produce(Request $request)
    {
        // Defining products and quantities here
        $products = [
            [
                'name' => 'Ko\'ylak',
                'quantity' => 30,
                'materials' => [
                    ['name' => 'Mato', 'quantity' => 24],
                    ['name' => 'Tigma', 'quantity' => 150],
                    ['name' => 'Ip', 'quantity' => 300],
                ],
            ],
            [
                'name' => 'Shim',
                'quantity' => 20,
                'materials' => [
                    ['name' => 'Mato', 'quantity' => 28],
                    ['name' => 'Ip', 'quantity' => 300],
                    ['name' => 'Zamok', 'quantity' => 20],
                ],
            ],
        ];

        $result = [];

        foreach ($products as $product) {
            $productData = [
                'product_name' => $product['name'],
                'product_qty' => $product['quantity'],
                'product_materials' => [],
            ];

            foreach ($product['materials'] as $material) {
                // Fetch data from the database based on material name
                $warehouseData = Material::where('material_name', $material['name'])->first();

                $remainingQuantity = $material['quantity'];

                foreach ($warehouseData as $warehouse) {
                    $reservedQuantity = $this->getReservedQuantity($warehouse, $productData['product_materials']);

                    $availableQuantity = $warehouse->remainder - $reservedQuantity;

                    // Check if the material is found in the database
                    $materialData = Material::where('material_name', $material['name'])->first();

                    if ($materialData) {
                        $reservedQuantity = $this->getReservedQuantity($warehouse, $productData['product_materials']);
                
                        // Ensure the warehouse data has the necessary fields
                        if (isset($warehouse->remainder) && isset($warehouse->price)) {
                            $availableQuantity = $warehouse->remainder - $reservedQuantity;
                
                            $quantityToFetch = min($remainingQuantity, $availableQuantity);
                
                            if ($quantityToFetch > 0) {
                                $productData['product_materials'][] = [
                                    'warehouse_id' => $warehouse->warehouse_id,
                                    'material_name' => $material['name'],
                                    'qty' => $quantityToFetch,
                                    'price' => $materialData->price,
                                ];
                
                                $remainingQuantity -= $quantityToFetch;
                            }
                        }
                    } else {
                        // We handle the case when the material is not found
                        // For example, you can log a message or add an entry in the result with default values.
                        Log::warning("Material '{$material['name']}' not found in the database.");

                        $productData['product_materials'][] = [
                            'warehouse_id' => null,
                            'material_name' => $material['name'],
                            'qty' => null,
                            'price' => null,
                        ];
                    }

                    // Stop if all required quantity is fetched
                    if ($remainingQuantity <= 0) {
                        break;
                    }
                }
            }

            $result[] = $productData;
        }

        $response = ['result' => $result];

        return response()->json($response);
    }

    // Helper function to get the reserved quantity of a material in previous products
    private function getReservedQuantity($warehouse, $previousMaterials)
    {
        $reservedQuantity = 0;

        foreach ($previousMaterials as $previousMaterial) {
            if ($previousMaterial['material_name'] === $warehouse->name &&
                $previousMaterial['warehouse_id'] === $warehouse->warehouse_id) {
                $reservedQuantity += $previousMaterial['qty'];
            }
        }

        return $reservedQuantity;
    }
}