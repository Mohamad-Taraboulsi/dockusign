<?php

namespace App\Http\Requests\ElectronicSignature;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_id' => 'sometimes|exists:document_recipients,id',
            'type' => 'sometimes|string|in:signature,initials,stamp,text_name,text_title,text_email,checkbox,dropdown,radio,note,attachment,date_signed',
            'label' => 'nullable|string|max:255',
            'placeholder' => 'nullable|string|max:255',
            'page_number' => 'sometimes|integer|min:1',
            'position_x' => 'sometimes|numeric|min:0|max:100',
            'position_y' => 'sometimes|numeric|min:0|max:100',
            'width' => 'sometimes|numeric|min:0|max:100',
            'height' => 'sometimes|numeric|min:0|max:100',
            'is_required' => 'boolean',
            'validation_rules' => 'nullable|array',
            'options' => 'nullable|array',
            'sort_order' => 'nullable|integer',
        ];
    }
}
