<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreWalletRequest extends FormRequest
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
            'owner_name' => 'required|string|max:255',
            'currency' => 'required|string|size:3',
        ];
    }
    public function messages(): array
    {
        return [
            'owner_name.required' => 'owner_name is required',
            'owner_name.string' => 'owner_name must be a string',
            'owner_name.max' => 'owner_name must not exceed 255 characters',
            'currency.required' => 'currency is required',
            'currency.string' => 'currency must be a string',
            'currency.size' => 'currency must be exactly 3 characters',
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
