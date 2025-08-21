<?php

namespace App\Http\Controllers\Registration;
use Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\registartion\LoginRequest ; // Ensure the namespace is correct
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Services\registartion\login; // Ensure the namespace is correct

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite as FacadesSocialite;

class LoginController extends Controller
{
    /**
     * Display a listing of the resource.
     */
        /**
     * Handle the registration of a new user.
     *
     * @param LoginRequest   $request
     * @return JsonResponse
     */

     protected $loginService;

     public function __construct(login $loginService)
     {
         $this->loginService = $loginService;
     }



public function login(LoginRequest $request)
{
    try {
        $validatedData = $request->validated();
        $loginData = $this->loginService->login($validatedData);

        return response()->json([
            'success' => true,
            'user' => $loginData['user'],
            'access_token' => $loginData['access_token']
        ]);

    } catch (\Exception $e) {
        $statusCode = $e->getCode() ?: 500;

        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], $statusCode);
    }
}


    public function logout(){
        auth()->user()->tokens()->delete();

        return response()->json([
          "message"=>"logged out"
        ]);
    }

}
