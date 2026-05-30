<?php

namespace App\Http\Requests\Mailer;

use Illuminate\Foundation\Http\FormRequest;

class ContactImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'emails_text' => ['nullable', 'string'],
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
            'batch_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
