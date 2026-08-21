<?php

namespace App\Http\Requests\Export;

use App\Services\Export\GeminiDocumentExtractor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExtractDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('export-document.edit') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,pdf'],
            'type_code' => [
                'required',
                'string',
                Rule::in(GeminiDocumentExtractor::UPLOADED_TYPES),
            ],
            'export_document_id' => ['nullable', 'integer', 'exists:export_documents,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a PDF or image to extract from.',
            'file.mimes'    => 'Upload a PDF or image (JPEG, PNG, WebP, GIF).',
        ];
    }
}
