<?php

namespace App\Http\Requests\ElectronicSignature;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:5000',
            'signing_order' => 'required|in:parallel,sequential',
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg',
            'recipients' => 'required|array|min:1',
            'recipients.*.email' => 'required|email|max:255',
            'recipients.*.name' => 'nullable|string|max:255',
            'recipients.*.role' => 'required|in:signer,cc',
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Please upload at least one document.',
            'recipients.required' => 'Please add at least one recipient.',
        ];
    }
}
