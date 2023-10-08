<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    //

    public function login(Request  $request)
    {
        // Check User Credentials For Login
        if (Auth::attempt($request->only(['name', 'password']))) {
            // create token for logged user
            $token = Auth::user()->createToken($request->input('name'))->plainTextToken;

            return response()->json(['result' => true, "user" => Auth::user(), "token" => $token], 200);
        }
        return response()->json(["result" => false, "error" => " ! password or name is wrong"], 401);
    }

    public function logout(Request $request)
    {
        try {
            Auth::user()->tokens()->delete();
            return response()->json('logouted successFully', 401);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
}
