<?php

namespace App\Http\Requests;

use App\Enums\TicketStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function status(): TicketStatus
    {
        return TicketStatus::from((string) $this->validated('status'));
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) ? $note : null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('note') === '') {
            $this->merge(['note' => null]);
        }
    }
}
