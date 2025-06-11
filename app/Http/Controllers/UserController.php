<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function createUser(Request $req)
    {
        $validation = Validator::make($req->all(), [
            'fullname' => 'required|max:50|min:3',
            'username' => 'required|max:20|min:3|unique:users',
            'email' => ['required', 'email', 'unique:users', 'lowercase'],
            'accountType' => 'required|in:savings,current,fixed',
            'password' => [
                'required',
                'min:8',
                'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/'
            ],
        ], [
            'password.regex' => 'Password must contain letters, numbers and special characters',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => '201',
                'msg' => $validation->errors()
            ]);
        }

        $user = User::create([
            'name' => $req->fullname,
            'username' => $req->username,
            'email' => $req->email,
            'accounttype' => $req->accountType,
            'balance' => 1000,
            'password' => Hash::make($req->password),
        ]);

        return response()->json([
            'status' => '200',
            'msg' => 'Account created successfully!'
        ]);
    }
}
