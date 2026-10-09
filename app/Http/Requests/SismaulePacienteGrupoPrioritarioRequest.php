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
            'comuna' => ['nullable', 'string', 'max:255'],
            'comuna_nombre' => ['nullable', 'string', 'max:255'],
            'comunas' => ['nullable', 'array'],
            'comunas.*' => ['string', 'max:255'],
            'grupos' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('server_url')) {
            $this->merge([
                'server_url' => rtrim((string) $this->input('server_url'), '/'),
            ]);
        }

        if ($this->filled('comunas')) {
            $this->merge([
                'comunas' => array_values(array_filter(array_map('strval', (array) $this->input('comunas')))),
            ]);
        } elseif ($this->filled('comuna')) {
            $this->merge([
                'comunas' => [(string) $this->input('comuna')],
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
