<?php

namespace App\Http\Requests\ElectronicSignature;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_file_id' => 'required|exists:document_files,id',
            'recipient_id' => 'required|exists:document_recipients,id',
            'type' => 'required|string|in:signature,initials,stamp,text_name,text_title,text_email,checkbox,dropdown,radio,note,attachment,date_signed',
            'label' => 'nullable|string|max:255',
            'placeholder' => 'nullable|string|max:255',
            'page_number' => 'required|integer|min:1',
            'position_x' => 'required|numeric|min:0|max:100',
            'position_y' => 'required|numeric|min:0|max:100',
            'width' => 'required|numeric|min:0|max:100',
            'height' => 'required|numeric|min:0|max:100',
            'is_required' => 'boolean',
            'validation_rules' => 'nullable|array',
            'options' => 'nullable|array',
            'sort_order' => 'nullable|integer',
        ];
    }
}
