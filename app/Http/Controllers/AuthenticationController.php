<?php

namespace App\Http\Controllers;

use App\Models\Classification;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use UltraMsg\WhatsAppApi;
use Laravel\Passport\Token;


class AuthenticationController extends Controller
{


   





    public function register(Request $request)
    {
        
        try {
            $request->validate([
                'name' => 'required|max:255',
                'phone' => 'required|regex:/^09[0-9]{8}$/',
                'password' => 'required|min:6|confirmed',
                'nameOfStore' => 'required',
                'classification_id' => 'required',
                'adress' => 'required',
                'language' => 'required|in:en,ar',
                'fcm_token' => 'required',
            ]);
        
            $deletedAccount = DB::table('deleted_accounts')->where('phone', $request->phone)->first();
        
            if ($deletedAccount) {
                $deletionPeriod = Carbon::parse($deletedAccount->deleted_at)->addDays(20);
        
                if (Carbon::now()->lessThanOrEqualTo($deletionPeriod)) {
                    return response([
                        'message' => trans('auth.attachedN'),
                    ], 403);
                } else {
                    DB::table('deleted_accounts')->where('phone', $request->phone)->delete();
                }
            }

            // Check if the phone number already exists and is verified
            $existingUser = User::where('phone', $request->phone)->first();

            if ($existingUser && $existingUser->is_verified) {
                return response([
                    'message' => trans('auth.already_verified'),
                ], 400);
            }

            // Create or update the user if not verified
            $user = User::updateOrCreate(
                ['phone' => $request->phone],
                [
                    'name' => $request->name,
                    'password' => Hash::make($request->password),
                    'adress' => $request->adress,
                    'classification_id' => $request->classification_id,
                    'nameOfStore' => $request->nameOfStore,
                    'fcm_token' => $request->fcm_token,
                    'language' => $request->language, // Ensure this is set correctly
                    'is_verified' => false,
                ]
            );

            $code = mt_rand(1000, 9999);
            $user->verification_code = $code;
            $user->save();

            // $this->sendCode($user->phone, $code, $user->name);

            // Check if today is the first day of the month
            if (Carbon::today()->day === 1) {
                $dateThreeDaysAgo = Carbon::now()->subDays(3);
                User::where('is_verified', false)
                    ->where('created_at', '<', $dateThreeDaysAgo)
                    ->delete();
            }

            return response([
                'message' => trans('auth.registration_success'),
                'user_id' => $user->id,
            ], 200);
            
        } catch (\Exception $e) {
            
            Log::error('Registration Error: ' . $e->getMessage());

            return response([
                'message' => trans('auth.registration_failed'), 
                // 'error' => $e->getMessage(),
            ], 403); 
        }
    }

    // public function register(Request $request)
    // {
    //     set_time_limit(1);
    //     try {
    //         $request->validate([
    //             'name' => 'required|max:255',
    //             'phone' => 'required|regex:/^[0-9]+$/',
    //             'password' => 'required|min:6|confirmed',
    //             'nameOfStore' => 'required',
    //             'classification_id' => 'required',
    //             'adress' => 'required',
    //             'fcm_token' => 'required',
    //         ]);

    //         // Check if the phone number already exists and is verified
    //         $existingUser = User::where('phone', $request->phone)->first();

    //         if ($existingUser && $existingUser->is_verified) {
    //             return response([
    //                 'message' => trans('auth.already_verified'),
    //             ], 400);
    //         }

    //         // Create or update the user if not verified
    //         $user = User::updateOrCreate(
    //             ['phone' => $request->phone],
    //             [
    //                 'name' => $request->name,
    //                 'password' => Hash::make($request->password),
    //                 'adress' => $request->adress,
    //                 'classification_id' => $request->classification_id,
    //                 'nameOfStore' => $request->nameOfStore,
    //                 'fcm_token' => $request->fcm_token,
    //                 'is_verified' => false,
    //             ]
    //         );

    //         $code = mt_rand(1000, 9999);
    //         $user->verification_code = $code;
    //         $user->save();

    //         // $this->sendCode($user->phone, $code, $user->name);

    //         // Check if today is the first day of the month
    //         if (Carbon::today()->day === 1) {
    //             $dateThreeDaysAgo = Carbon::now()->subDays(3);
    //             User::where('is_verified', false)
    //                 ->where('created_at', '<', $dateThreeDaysAgo)
    //                 ->delete();
    //         }

    //         return response([
    //             'message' => trans('auth.registration_success'),
    //             'user_id' => $user->id,
    //         ], 200);
    //     } catch (\Exception $e) {
    //         // Log the exception for debugging
    //         Log::error('Registration Error: '.$e->getMessage());

    //         return response([
    //             'message' => trans('auth.registration_failed'), // Make sure this key exists in your translation files
    //             'error' => $e->getMessage(),
    //         ], 500); // Return a 500 Internal Server Error
    //     }
    // }







    /////////////////////////////////////////////////////////////////////
    function verifyCode(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'code' => 'required',
        ]);

        $user = User::findOrFail($request->user_id);

        // if ($user->verification_code == $request->code) {
        if ($user->verification_code) {
            $user->is_verified = true;
            $user->save();
            $token = $user->createToken('auth_token')->accessToken;

            return response([
                'message' => trans('auth.verification_success'),
                'token' => $token,
            ], 200);
        } else {
            return response([
                'message' => trans('auth.verification_failed'),
            ], 422);
        }
    }

    public function resendCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|exists:users,phone',
        ]);

        $user = User::where('phone', $request->phone)->firstOrFail();

        if ($user->is_verified) {
            return response([
                'message' => trans('auth.already_verified'),
            ], 400);
        }

        $code = mt_rand(1000, 9999);
        $user->verification_code = $code;
        $user->save();

        $this->sendCode($user->phone, $code, $user->name);

        return response([
            'message' => trans('auth.code_resent'),
        ], 200);
    }
    /////////////////////////////////////////////
    public function logout()
    {
          // Assuming the user is logged in
          $user = Auth::user();

          // Revoke all tokens for the user
          Token::where('user_id', $user->id)->update(['revoked' => true]);
        return response()->json([
            'message' => trans('auth.logout')
        ]);
   
}
    // public function logout()
    // {
    //     User::find(Auth::id())->tokens()->delete();
    //     return response([
    //         'message' => trans('auth.logout')
    //     ]);
    // }
    //////////////////////////////////////
    public function forgetPassword(Request $request)
    {
        $request->validate([
            'phone' => 'required',
        ]);

        $user = User::where('phone', $request->phone)->first();
        if (!$user) {
            return response()->json([
                'message' => trans('auth.wrongNumber')
            ]);
        }
        $code = mt_rand(1000, 9999);
        $user->verification_code = $code;
        $user->save();

        // $this->sendCode($user['phone'], $code,$user['name']);

        return response([
            'message' => trans('auth.codeSent'),
            'user_id' => $user->id,

        ], 200);
    }
    ///////////////////////////////////////////////////
    public function verifyForgetPassword(Request $request)
    {

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'code' => 'required',
        ]);

        $user = User::findOrFail($request->user_id);

        // if ($user->verification_code == $request->code) {
        if ($user->verification_code) {



            return response([
                'message' => trans('auth.codeCorrect')

            ], 200);
        } else {
            return response([
                'message' => trans('auth.codeWrong')
            ], 422);
        }
    }

    /////////////////////////////////////////////////////
    public function resatPassword(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'password' => 'required|min:6|confirmed',
            'fcm_token' => 'required',
        ]);
        $user = User::findOrFail($request->user_id);
        $user->update(['password' => Hash::make($request['password'])]);

        $token = $user->createToken('auth_token')->accessToken;
        $user->fcm_token = $request['fcm_token'];
        $user->save();
        return response()->json([
            'message' => trans('auth.editPassword'),
            'token' => $token,
        ]);
    }
    ///////////////////////////////////////////
    public function resatPasswordEnternal(Request $request)
    {

        $request->validate([
            'password' => 'required|min:6',
            'NewPassword' => 'required|min:6|confirmed',
        ]);


        if (Hash::check($request->password, auth()->user()->password)) {

            auth()->user()->update(['password' => Hash::make($request['NewPassword'])]);
            return response()->json([
                'message' => trans('auth.editPassword'),
            ]);
        } else {
            return response()->json([
                'message' => trans('auth.wrongPassword')
            ]);
        }
    }



    /////////////////////////////////////////
    public function sendCode($phoneNumber, $code, $name)
    {
        require_once(base_path('vendor/autoload.php'));
        $ultramsg_token = env('WHATSAPP_TOKEN');
        $instance_id = env('WHATSAPP_ID');
        $client = new WhatsAppApi($ultramsg_token, $instance_id);
        $number = "+963" . substr($phoneNumber, 1, 9);
        $to = $number;

        $body = trans('auth.Hi') . $name . trans('auth.him') . $code;
        $client->sendChatMessage($to, $body);
        // return $this->success(null, 'we send the code');
    }

    /////////////////////////////////////


    // public function login(Request $request){
    //     $request->validate([
    //         'phone' => 'required|regex:/^[0-9]+$/',
    //         'password' => 'required|min:6',
    //         'fcm_token' => 'required'
    //     ]);

    //     $user = User::where('phone', $request->phone)->first();

    //     if (!$user || !Hash::check($request->password, $user->password)) {
    //         return response([
    //             'message' => trans('auth.wrongLogin')
    //         ], 403);
    //     }
    //     $user->fcm_token = $request->fcm_token; 
    //     $user->save();

    //     $user->type = $user->role == 0 ? 'admin' : 'user';

    //     $token = $user->createToken('auth_token')->accessToken;

    //     return response()->json([
    //         'user' => $user,
    //         'token' => $token
    //     ]);
    // }

    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|regex:/^[0-9]+$/',
            'password' => 'required|min:6',
            'fcm_token' => 'required'
        ]);

        $user = User::withTrashed()->where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response([
                'message' => trans('auth.wrongLogin')
            ], 403);
        }

        $deletedAccount = DB::table('deleted_accounts')->where('phone', $request->phone)->first();

        if ($deletedAccount) {
            $deletionPeriod = Carbon::parse($deletedAccount->deleted_at)->addDays(20);

            if (Carbon::now()->lessThanOrEqualTo($deletionPeriod)) {
                $user->restore();
                DB::table('deleted_accounts')->where('phone', $request->phone)->delete();
            } else {
                return response([
                    'message' => trans('auth.NoAttachedN'),
                ], 403);
            }
        }

        $user->fcm_token = $request->fcm_token;
        $user->save();

        $user->type = $user->role == 0 ? 'admin' : 'user';

        $token = $user->createToken('auth_token')->accessToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }
    public function userInfo()
    {
        return response()->json([
            'user' => User::where('id', Auth::user()->id)->with('classification')->get(),
        ]);
    }


    public function allUsers(Request $request)
    {
        $request->validate([
            'sort' => 'required|boolean',
        ]);

        $userQuery = User::where('role', 1)->with('classification');

        if ($request->sort) {
            $userQuery = $userQuery->withCount('order')->orderBy('order_count', 'desc');
        }

        $users = $userQuery->get();

        return response()->json([
            'users' => $users
        ]);
    }

    public function choseLanguage(Request $request)
    {
        $request->validate([
            'language' => 'required|in:en,ar',
        ]);

        $user = Auth::user();
        $user->language = $request->language;
        $user->save();

        return response()->json([
            'message' => trans('auth.editLang'),
        ], 200);
    }




    public function deleteUser()
    {
       // Get the authenticated user
    $user = Auth::user();

    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    // Check for pending orders
    $pendingOrders = Order::where('user_id', $user->id)
                          ->where('state', '!=', 'received')
                          ->exists();

    if ($pendingOrders) {
        return response()->json(['message' => trans('auth.cannotDA')], 403);
    }

    // Store deleted account info
    DB::table('deleted_accounts')->insert([
        'user_id' => $user->id,
        'phone' => $user->phone,
        'deleted_at' => Carbon::now(),
    ]);

    // Soft delete the user
    $user->delete();

    return response()->json(['message' => trans('auth.delatedAcc')], 200);
    }
}
