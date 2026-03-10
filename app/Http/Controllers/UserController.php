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
            'fullname' => [
                'required',
                'max:50',
                'min:3',
                'regex:/^[a-zA-Z]+(\s+[a-zA-Z]+)+$/'
            ],
            'email' => ['required', 'email', 'unique:users', 'lowercase'],
            'accountType' => 'required|in:savings,current,fixed',
            'password' => [
                'required',
                'min:8',
                'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/'
            ],
        ], [
            'fullname.regex' => 'Please enter your first name and last name separated by a space. No numbers or special characters allowed.',
            'password.regex' => 'Password must contain letters, numbers and special characters',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => '422',
                'msg' => $validation->errors()
            ]);
        }

        User::create([
            'name' => $req->fullname,
            'email' => $req->email,
            'account_type' => $req->accountType,
            'account_number' => $this->generateAccountNumber(),
            'balance' => 0,
            'password' => Hash::make($req->password),
        ]);

        return response()->json([
            'status' => '200',
            'msg' => 'Account created successfully!'
        ]);
    }

    private function generateAccountNumber(): string
    {
        do {
            $accountNumber = str_pad(random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        } while (User::where('account_number', $accountNumber)->exists());

        return $accountNumber;
    }

    public function login(Request $req)
    {
        $validation = Validator::make($req->all(), [
            'email' => ['required', 'email', 'lowercase'],
            'password' => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => '422',
                'msg' => $validation->errors()
            ]);
        }

        $user = User::where('email', $req->email)->first();

        if (!$user || !Hash::check($req->password, $user->password)) {
            return response()->json([
                'status' => '401',
                'msg' => 'Invalid credentials'
            ]);
        }

        $token = $user->createToken('vaultly_token')->plainTextToken;

        return response()->json([
            'status' => '200',
            'msg' => 'Login successful!',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'profile_picture' => $user->profile_picture,
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'balance' => $user->balance,
                'account_type' => $user->account_type,
                'account_number' => $user->account_number,
                'created_at' => $user->created_at,
                'next_of_kin_name' => $user->next_of_kin_name,
                'next_of_kin_phone' => $user->next_of_kin_phone,
                'profile_picture' => $user->profile_picture,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'msg' => 'Logged out'
        ]);
    }
}
