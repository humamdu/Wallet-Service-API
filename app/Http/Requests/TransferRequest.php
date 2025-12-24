<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class TransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source_wallet_id' => 'required|integer|exists:wallets,id',
            'target_wallet_id' => 'required|integer|exists:wallets,id',
            'amount' => ['required', 'numeric'],
            'idempotency_key.required' => 'idempotency_key is required',
        ];
    }
    public function messages(): array
    {
        return [
            'source_wallet_id.required' => 'source_wallet_id is required',
            'source_wallet_id.integer' => 'source_wallet_id must be an integer',
            'source_wallet_id.exists' => 'source_wallet_id does not exist',
            'target_wallet_id.required' => 'target_wallet_id is required',
            'target_wallet_id.integer' => 'target_wallet_id must be an integer',
            'target_wallet_id.exists' => 'target_wallet_id does not exist',
            'amount.required' => 'amount is required',
            'amount.numeric' => 'Invalid amount format',
            'idempotency_key.required' => 'idempotency_key is required',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();
        throw new HttpResponseException(response()->json([
            'message' => 'Validation Failed',
            'errors' => $errors
        ], 422));
    }

}
