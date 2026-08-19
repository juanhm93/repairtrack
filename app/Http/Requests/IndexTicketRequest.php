<?php

namespace App\Http\Requests;

use App\Enums\TicketStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTicketRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array{status: TicketStatus|null, q: string|null}
     */
    public function filters(): array
    {
        $status = $this->validated('status');
        $query = $this->validated('q');

        if ($status instanceof TicketStatus) {
            $enum = $status;
        } elseif (is_string($status) && $status !== '') {
            $enum = TicketStatus::from($status);
        } else {
            $enum = null;
        }

        return [
            'status' => $enum,
            'q' => is_string($query) && $query !== '' ? $query : null,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->blankToNull('status'),
            'q' => $this->blankToNull('q'),
        ]);
    }

    private function blankToNull(string $key): mixed
    {
        $value = $this->input($key);

        return $value === '' ? null : $value;
    }
}
