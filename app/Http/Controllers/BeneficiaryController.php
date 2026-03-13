<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BeneficiaryController extends Controller
{
    public function index(Request $request)
    {
        $beneficiaries = Beneficiary::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => '200',
            'beneficiaries' => $beneficiaries,
        ]);
    }

    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'account_number' => ['required', 'string', 'size:12'],
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => '422',
                'msg' => $validation->errors(),
            ]);
        }

        $owner = $request->user();

        if ($owner->account_number === $request->account_number) {
            return response()->json([
                'status' => '400',
                'msg' => 'You cannot save your own account as beneficiary.',
            ]);
        }

        $recipient = User::where('account_number', $request->account_number)->first();

        if (!$recipient) {
            return response()->json([
                'status' => '404',
                'msg' => 'Account not found.',
            ]);
        }

        $existing = Beneficiary::where('user_id', $owner->id)
            ->where('account_number', $request->account_number)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => '409',
                'msg' => 'Beneficiary already exists.',
            ]);
        }

        $beneficiary = Beneficiary::create([
            'user_id' => $owner->id,
            'account_name' => $recipient->name,
            'account_number' => $request->account_number,
            'bank_name' => 'Vaultly Bank',
            'bank_code' => '999001',
        ]);

        return response()->json([
            'status' => '200',
            'msg' => 'Beneficiary added successfully!',
            'beneficiary' => $beneficiary,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $beneficiary = Beneficiary::where('user_id', $request->user()->id)->where('id', $id)->first();

        if (!$beneficiary) {
            return response()->json([
                'status' => '404',
                'msg' => 'Beneficiary not found.',
            ]);
        }

        $validation = Validator::make($request->all(), [
            'account_number' => ['required', 'string', 'size:12'],
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => '422',
                'msg' => $validation->errors(),
            ]);
        }

        $owner = $request->user();

        if ($owner->account_number === $request->account_number) {
            return response()->json([
                'status' => '400',
                'msg' => 'You cannot save your own account as beneficiary.',
            ]);
        }

        $recipient = User::where('account_number', $request->account_number)->first();

        if (!$recipient) {
            return response()->json([
                'status' => '404',
                'msg' => 'Account not found.',
            ]);
        }

        $duplicate = Beneficiary::where('user_id', $owner->id)
            ->where('id', '!=', $beneficiary->id)
            ->where('account_number', $request->account_number)
            ->first();

        if ($duplicate) {
            return response()->json([
                'status' => '409',
                'msg' => 'Beneficiary already exists.',
            ]);
        }

        $beneficiary->update([
            'account_name' => $recipient->name,
            'account_number' => $request->account_number,
            'bank_name' => 'Vaultly Bank',
            'bank_code' => '999001',
        ]);

        return response()->json([
            'status' => '200',
            'msg' => 'Beneficiary updated successfully!',
            'beneficiary' => $beneficiary,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $beneficiary = Beneficiary::where('user_id', $request->user()->id)->where('id', $id)->first();

        if (!$beneficiary) {
            return response()->json([
                'status' => '404',
                'msg' => 'Beneficiary not found.',
            ]);
        }

        $beneficiary->delete();

        return response()->json([
            'status' => '200',
            'msg' => 'Beneficiary deleted successfully!',
        ]);
    }
}