<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            ]);
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
            ]);
        }

        $user = $request->user();

        // Remove previous local file when applicable.
        $this->deleteLocalProfileImageIfApplicable($user->profile_picture);

        $path = $request->file('profile_picture')->store('profile-pictures', 'public');
        $user->profile_picture = Storage::url($path);
        $user->save();

        return response()->json([
            'status' => '200',
            'msg' => 'Profile picture uploaded successfully!',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function deleteProfilePicture(Request $request)
    {
        $user = $request->user();

        $this->deleteLocalProfileImageIfApplicable($user->profile_picture);
        $user->profile_picture = null;
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
            'has_pin' => !is_null($user->transaction_pin),
        ];
    }

    private function deleteLocalProfileImageIfApplicable(?string $profilePictureUrl): void
    {
        if (!$profilePictureUrl) {
            return;
        }

        $storageMarker = '/storage/';
        $markerPosition = strpos($profilePictureUrl, $storageMarker);

        if ($markerPosition === false) {
            return;
        }

        $relativePath = substr($profilePictureUrl, $markerPosition + strlen($storageMarker));

        if ($relativePath !== '') {
            Storage::disk('public')->delete($relativePath);
        }
    }
}
