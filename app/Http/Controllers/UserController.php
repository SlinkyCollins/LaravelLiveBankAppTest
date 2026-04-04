<?php

namespace App\Http\Controllers;

use App\Models\User;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

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
            'user' => $this->userPayload($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    public function getProfile(Request $request)
    {
        return response()->json([
            'status' => '200',
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $payload = [
            'name' => $request->input('name', $request->input('fullname')),
            'next_of_kin_name' => $request->input('next_of_kin_name'),
            'next_of_kin_phone' => $request->input('next_of_kin_phone'),
            'account_type' => $request->input('account_type', $request->input('accountType')),
        ];

        $validation = Validator::make($payload, [
            'name' => [
                'required',
                'max:50',
                'min:3',
                'regex:/^[a-zA-Z]+(\s+[a-zA-Z]+)+$/'
            ],
            'next_of_kin_name' => ['nullable', 'string', 'min:3', 'max:100'],
            'next_of_kin_phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s]{7,20}$/'],
            'account_type' => ['nullable', 'in:savings,current,fixed'],
        ], [
            'name.regex' => 'Please enter your first name and last name separated by a space. No numbers or special characters allowed.',
            'next_of_kin_phone.regex' => 'Next of kin phone format is invalid.',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => '422',
                'msg' => $validation->errors(),
            ], 422);
        }

        $user->name = $payload['name'];
        $user->next_of_kin_name = $payload['next_of_kin_name'] ?: null;
        $user->next_of_kin_phone = $payload['next_of_kin_phone'] ?: null;

        if (!is_null($payload['account_type'])) {
            $user->account_type = $payload['account_type'];
        }

        $user->save();

        return response()->json([
            'status' => '200',
            'msg' => 'Profile updated successfully!',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function uploadProfilePicture(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'profile_picture' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => '422',
                'msg' => $validation->errors(),
            ], 422);
        }

        $user = $request->user();

        try {
            if ($user->profile_picture_public_id) {
                Cloudinary::destroy($user->profile_picture_public_id);
            }

            $uploadedAsset = Cloudinary::upload(
                $request->file('profile_picture')->getRealPath(),
                [
                    'folder' => 'vaultly/profile-pictures',
                    'resource_type' => 'image',
                ]
            );

            $securePath = $uploadedAsset->getSecurePath();
            $publicId = $uploadedAsset->getPublicId();

            if (!is_string($securePath) || trim($securePath) === '' || !is_string($publicId) || trim($publicId) === '') {
                throw new \RuntimeException('Cloudinary upload returned an invalid response payload.');
            }

            $user->profile_picture = $securePath;
            $user->profile_picture_public_id = $publicId;
            $user->save();
        } catch (Throwable $exception) {
            Log::error('Profile picture upload failed.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return response()->json([
                'status' => '500',
                'msg' => 'Failed to upload profile picture. Please try again.',
                'debug_error' => $exception->getMessage(),
                'debug_exception' => get_class($exception),
                'debug_file' => $exception->getFile(),
                'debug_line' => $exception->getLine(),
            ], 500);
        }

        return response()->json([
            'status' => '200',
            'msg' => 'Profile picture uploaded successfully!',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function deleteProfilePicture(Request $request)
    {
        $user = $request->user();

        if ($user->profile_picture_public_id) {
            try {
                Cloudinary::destroy($user->profile_picture_public_id);
            } catch (Throwable $exception) {
                Log::error('Profile picture delete failed.', [
                    'user_id' => $user->id,
                    'public_id' => $user->profile_picture_public_id,
                    'error' => $exception->getMessage(),
                ]);

                return response()->json([
                    'status' => '500',
                    'msg' => 'Failed to remove profile picture. Please try again.',
                    'debug_error' => $exception->getMessage(),
                ], 500);
            }
        }

        $user->profile_picture = null;
        $user->profile_picture_public_id = null;
        $user->save();

        return response()->json([
            'status' => '200',
            'msg' => 'Profile picture removed successfully!',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'msg' => 'Logged out'
        ]);
    }

    public function balance(Request $request)
    {
        return response()->json([
            'status' => '200',
            'balance' => $request->user()->balance,
        ]);
    }

    public function setPin(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'pin' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ], [
            'pin.regex' => 'PIN must be exactly 4 digits.',
            'pin_confirmation.same' => 'PIN confirmation does not match.',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => '422',
                'msg' => $validation->errors()
            ]);
        }

        $user = $request->user();

        if ($user->transaction_pin) {
            return response()->json([
                'status' => '400',
                'msg' => 'Transaction PIN already set. Use change PIN instead.'
            ]);
        }

        $user->transaction_pin = Hash::make($request->pin);
        $user->save();

        return response()->json([
            'status' => '200',
            'msg' => 'Transaction PIN set successfully!'
        ]);
    }

    public function changePin(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'current_pin' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'new_pin' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'new_pin_confirmation' => ['required', 'same:new_pin'],
        ], [
            'current_pin.regex' => 'Current PIN must be exactly 4 digits.',
            'new_pin.regex' => 'PIN must be exactly 4 digits.',
            'new_pin_confirmation.same' => 'PIN confirmation does not match.',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => '422',
                'msg' => $validation->errors()
            ]);
        }

        $user = $request->user();

        if (!$user->transaction_pin) {
            return response()->json([
                'status' => '400',
                'msg' => 'No PIN set yet. Please set your PIN first.'
            ]);
        }

        if (!Hash::check($request->current_pin, $user->transaction_pin)) {
            return response()->json([
                'status' => '401',
                'msg' => 'Current PIN is incorrect.'
            ]);
        }

        $user->transaction_pin = Hash::make($request->new_pin);
        $user->save();

        return response()->json([
            'status' => '200',
            'msg' => 'Transaction PIN changed successfully!'
        ]);
    }

    public function changePassword(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'new_password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            ],
            'new_password_confirmation' => ['required', 'same:new_password'],
        ], [
            'new_password.regex' => 'Password must contain letters, numbers and special characters',
            'new_password_confirmation.same' => 'Password confirmation does not match.',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => '422',
                'msg' => $validation->errors(),
            ]);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => '401',
                'msg' => 'Current password is incorrect.',
            ]);
        }

        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'status' => '400',
                'msg' => 'New password must be different from current password.',
            ]);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status' => '200',
            'msg' => 'Password changed successfully!',
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
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
            'profile_picture_public_id' => $user->profile_picture_public_id,
            'has_pin' => !is_null($user->transaction_pin),
        ];
    }
}
