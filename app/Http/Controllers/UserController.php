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
                'status' => '422',
                'msg' => $validation->errors()
            ]);
        }

        $user = User::create([
            'name' => $req->fullname,
            'email' => $req->email,
            'account_type' => $req->accountType,
            'account_number' => $this->generateAccountNumber(),
            'balance' => 1000,
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
            $accountNumber = str_pad(mt_rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
        } while (User::where('account_number', $accountNumber)->exists());

        return $accountNumber;
    }
}
