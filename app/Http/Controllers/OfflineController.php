<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Product;
use App\Variation;
use App\Contact;
use App\BusinessLocation;
use App\Utils\ProductUtil;
use DB;

class OfflineController extends Controller
{
    protected $productUtil;

    public function __construct(ProductUtil $productUtil)
    {
        $this->productUtil = $productUtil;
    }

    public function getProducts(Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            $location_id = $request->get('location_id');

            if (!$business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $query = Product::where('business_id', $business_id)
                ->where('type', '!=', 'modifier')
                ->with(['variations', 'variations.product_variation', 'unit', 'brand', 'category']);

            if ($location_id) {
                $query->ForLocation($location_id);
            }

            $products = $query->select([
                'id', 'name', 'sku', 'type', 'unit_id', 'brand_id', 
                'category_id', 'sub_category_id', 'enable_stock', 'alert_quantity',
                'image', 'product_description'
            ])
            ->limit(1000)
            ->get();

            $formatted_products = [];

            foreach ($products as $product) {
                $product_data = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'type' => $product->type,
                    'unit_id' => $product->unit_id,
                    'brand_id' => $product->brand_id,
                    'category_id' => $product->category_id,
                    'enable_stock' => $product->enable_stock,
                    'alert_quantity' => $product->alert_quantity,
                    'image' => $product->image,
                    'description' => $product->product_description,
                    'variations' => []
                ];

                if ($product->variations) {
                    foreach ($product->variations as $variation) {
                        $variation_data = [
                            'id' => $variation->id,
                            'name' => $variation->product_variation->name ?? '',
                            'sub_sku' => $variation->sub_sku,
                            'default_purchase_price' => $variation->default_purchase_price,
                            'default_sell_price' => $variation->default_sell_price,
                            'sell_price_inc_tax' => $variation->sell_price_inc_tax,
                        ];

                        if ($location_id) {
                            $qty_available = $this->productUtil->num_uf(
                                $this->productUtil->get_product_stock_for_location(
                                    $product->id,
                                    $variation->id,
                                    $location_id
                                )
                            );
                            $variation_data['qty_available'] = $qty_available;
                        }

                        $product_data['variations'][] = $variation_data;
                    }
                }

                $formatted_products[] = $product_data;
            }

            return response()->json([
                'success' => true,
                'data' => $formatted_products
            ]);

        } catch (\Exception $e) {
            \Log::error('Get products for offline error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getCustomers(Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');

            if (!$business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $customers = Contact::where('business_id', $business_id)
                ->where('type', 'customer')
                ->select([
                    'id', 'name', 'mobile', 'email', 'customer_group_id',
                    'credit_limit', 'pay_term_number', 'pay_term_type'
                ])
                ->limit(500)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $customers
            ]);

        } catch (\Exception $e) {
            \Log::error('Get customers for offline error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customers: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getLocations(Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');

            if (!$business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $locations = BusinessLocation::where('business_id', $business_id)
                ->select(['id', 'name', 'landmark', 'city', 'state', 'country'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $locations
            ]);

        } catch (\Exception $e) {
            \Log::error('Get locations for offline error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch locations: ' . $e->getMessage()
            ], 500);
        }
    }

    public function health()
    {
        return response()->json([
            'success' => true,
            'status' => 'online',
            'timestamp' => time()
        ]);
    }
}
