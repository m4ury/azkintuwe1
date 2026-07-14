<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SismaulePacienteGrupoPrioritarioRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'server_url' => ['required', 'url', Rule::in($this->configuredServerUrls())],
            'comuna' => ['required', 'string', 'max:255'],
            'comuna_nombre' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('server_url')) {
            $this->merge([
                'server_url' => rtrim((string) $this->input('server_url'), '/'),
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function configuredServerUrls(): array
    {
        return collect(config('app.servers', []))
            ->pluck('url')
            ->map(fn (mixed $url): string => rtrim((string) $url, '/'))
            ->all();
    }
}
