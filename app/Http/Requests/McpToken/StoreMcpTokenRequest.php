<?php

declare(strict_types=1);

namespace App\Http\Requests\McpToken;

use App\Models\McpToken;
use Illuminate\Foundation\Http\FormRequest;

class StoreMcpTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('create', McpToken::class);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'expires_in_days' => 'vencimiento',
        ];
    }
}
