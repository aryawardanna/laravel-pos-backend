<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // index api
    public function index()
    {
        // $products = Product::paginate(10);
        $products = Product::get();
        return response()->json(
            [
                'success' => 'success',
                'data' => $products
            ]
        );

    }
}
