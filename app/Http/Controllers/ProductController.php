<?php

namespace App\Http\Controllers;

use App\Models\ClassificationProduct;
use App\Models\PointsProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class ProductController extends Controller
{

    public function AddProduct(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:products',
            'price' => 'required|numeric',
            'description' => 'required',
            'is_public' => 'required|boolean',
            'classifications' => 'required_if:is_public,false|array',
            'classifications.*' => 'required_if:is_public,false|string',
            'type' => 'nullable|string',
            'points' => 'required|integer',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,bmp|max:4096',

        ]);

        $imageUrls = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $key => $image) {
                $imageName = time() . $key . '.' . $image->extension();
                $image->move(public_path('uploads/'), $imageName);
                $imageUrls[] = URL::asset('uploads/' . $imageName);
            }
        } else {
            $imageUrls = null;
        }

        $product = Product::create([
            'name' => $request->input('name'),
            'price' => $request->input('price'),
            'type' => $request->type,
            'points' => $request->points,
            'description' => $request->input('description'),
            'images' => $imageUrls ? json_encode($imageUrls) : null,
            'is_public' => $request->is_public,
        ]);

        if (!$request->is_public) {
            foreach ($request->input('classifications') as $classification) {
                ClassificationProduct::create([
                    'classification_id' => $classification,
                    'product_id' => $product->id,
                ]);
            }
        }

        return response()->json([
            'message' => trans('Complaints.Created'),
            'product' => $product,
        ], 201);
    }




     public function ProdctsDetails($product_id)
    {
        $iteam = Product::where('id', $product_id)->first();

        if (!$iteam) {
            return response()->json([
                'message' => trans('product.noProduct'),

            ], 404);
        }
        return response()->json([

            'the prodct:' => $iteam,
        ], 200);
    }


    public function updateProduct(Request $request, $product_id)
    {
        $product = Product::findOrFail($product_id);

        $request->validate([
            'name' => 'required|unique:products,name,' . $product->id,
            'price' => 'required|numeric',
            'description' => 'required',
            'is_public' => 'required|boolean',
            'classifications' => 'required_if:is_public,false|array',
            'classifications.*' => 'required_if:is_public,false|string',
            'type' => 'nullable|string',
            'points' => 'required|integer',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,bmp|max:4096',
        ]);

        $imageUrls = [];
        if ($request->hasFile('images')) {
            // Delete old images
            $oldImages = json_decode($product->images, true);
            if ($oldImages) {
                foreach ($oldImages as $oldImage) {
                    if (file_exists(public_path($oldImage))) {
                        unlink(public_path($oldImage));
                    }
                }
            }

            // Upload new images
            foreach ($request->file('images') as $key => $image) {
                $imageName = time() . $key . '.' . $image->extension();
                $image->move(public_path('uploads/'), $imageName);
                $imageUrls[] = URL::asset('uploads/' . $imageName);
            }
        } else {
            $imageUrls = json_decode($product->images, true);
        }


        $product->update([
            'name' => $request->input('name'),
            'price' => $request->input('price'),
            'type' => $request->type,
            'points' => $request->points,
            'description' => $request->input('description'),
            'is_public' => $request->is_public,
            'images' => $imageUrls ? json_encode($imageUrls) : null,
        ]);


        ClassificationProduct::where('product_id', $product->id)->delete();
        if (!$request->is_public) {
            foreach ($request->input('classifications') as $classification) {
                ClassificationProduct::create([
                    'classification_id' => $classification,
                    'product_id' => $product->id,
                ]);
            }
        }

        return response()->json([
            'message' => trans('normalOrder.updated'),
            'product' => $product,
        ], 200);
    }

    public function deleteProduct($product_id)
    {
        $product = Product::findOrFail($product_id);
        $product->delete(); // This will perform a soft delete


        return response()->json(['message' => trans('product.deleteProduct')], 200);
    }


    ////////////////////////////////////////

    public function productsAdmin($type)
    {
  
        $sukerProducts = Product::where('type', $type)->with('classification')->get();

        if ($sukerProducts->isNotEmpty()) {

            $sukerProducts->transform(function ($sukerProduct) {
                $sukerProduct->images = json_decode($sukerProduct->images);
                return $sukerProduct;
            });
        
      
            return response()->json([
                'the_product' => $sukerProducts,
            ], 200);
        }
        //fe
        return response()->json([
            'the_product' => $sukerProducts,
        ], 200);
    }
    

    //////////////////////////////////////

    public function Products($type)
    {
        $user = Auth::user();
        $classification_id = $user->classification_id;

        $products = Product::where('displayOrNot', true)
            ->where(function ($query) use ($classification_id) {
                $query->where('is_public', true)
                    ->orWhereHas('classification', function ($q) use ($classification_id) {
                        $q->where('classification_id', $classification_id);
                        // ->where('displayOrNot', true);
                    });
            })
            ->where('type', $type)
            ->get();


        $products = $products->map(function ($product) {
            $product->images = json_decode($product->images);
            return $product;
        });

        return response()->json([
            'the_product' => $products,
        ], 200);
    }

    public function onOffProduct($product_id)
{
    // Use findOrFail to automatically handle not found cases
    $product = Product::findOrFail($product_id);

    // Toggle the display state
    $product->displayOrNot = !$product->displayOrNot;
    $product->save();

    // Prepare response message
    $state = $product->displayOrNot ? trans('product.onProduct') : trans('product.offProduct');

    // Return success response
    return response()->json([
        'message' => $state,
    ], 200); // HTTP 200 OK
}

    public function searchProduct($name){
        $theProduct= Product::where('name','like','%' . $name . '%')->get();
        return response()->json([
            'theProduct :' => $theProduct,
        ]);
    }
    public function searchPoinstProduct($name){
        $theProduct= PointsProduct::where('name','like','%' . $name . '%')->get();
        return response()->json([
            ' theProduct :' => $theProduct,
        ]);
    }
}


