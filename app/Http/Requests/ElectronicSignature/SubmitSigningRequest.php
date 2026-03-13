<?php

namespace App\Http\Requests\ElectronicSignature;

use Illuminate\Foundation\Http\FormRequest;

class SubmitSigningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fields' => 'required|array',
            'fields.*' => 'nullable',
            'files' => 'nullable|array',
            'files.*' => 'nullable|file|max:5120',
        ];
    }
}
