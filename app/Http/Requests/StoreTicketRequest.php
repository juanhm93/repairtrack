<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
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
            'customer_name' => [
                Rule::requiredIf(! $this->customerAlreadyExists()),
                'nullable',
                'string',
                'max:255',
            ],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['required', 'email', 'max:255'],
            'device_type' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'reported_issue' => ['required', 'string'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'received_at' => ['required', 'date'],
            'estimated_delivery_at' => ['nullable', 'date', 'after_or_equal:received_at'],
        ];
    }

    /**
     * @return array{
     *     customer_name?: string|null,
     *     customer_email: string,
     *     customer_phone?: string|null,
     *     device_type: string,
     *     brand?: string|null,
     *     model?: string|null,
     *     serial_number?: string|null,
     *     reported_issue: string,
     *     estimated_cost?: mixed,
     *     received_at: string,
     *     estimated_delivery_at?: string|null
     * }
     */
    public function payload(): array
    {
        /** @var array{
         *     customer_name?: string|null,
         *     customer_email: string,
         *     customer_phone?: string|null,
         *     device_type: string,
         *     brand?: string|null,
         *     model?: string|null,
         *     serial_number?: string|null,
         *     reported_issue: string,
         *     estimated_cost?: mixed,
         *     received_at: string,
         *     estimated_delivery_at?: string|null
         * } $data */
        $data = $this->safe()->except(['photos']);

        if (! $this->exists('customer_name')) {
            unset($data['customer_name']);
        }

        if (! $this->exists('customer_phone')) {
            unset($data['customer_phone']);
        }

        return $data;
    }

    /**
     * @return list<UploadedFile>
     */
    public function photos(): array
    {
        $photos = $this->file('photos');

        if ($photos instanceof UploadedFile) {
            return $photos->isValid() ? [$photos] : [];
        }

        if (! is_array($photos)) {
            return [];
        }

        $files = [];

        foreach ($photos as $photo) {
            if ($photo->isValid()) {
                $files[] = $photo;
            }
        }

        return $files;
    }

    protected function prepareForValidation(): void
    {
        $hasName = $this->exists('customer_name');
        $hasPhone = $this->exists('customer_phone');
        $email = $this->input('customer_email');

        $this->merge([
            'customer_email' => is_string($email) ? Str::lower($email) : $email,
            ...($hasName ? ['customer_name' => $this->blankToNull('customer_name')] : []),
            ...($hasPhone ? ['customer_phone' => $this->blankToNull('customer_phone')] : []),
            'brand' => $this->blankToNull('brand'),
            'model' => $this->blankToNull('model'),
            'serial_number' => $this->blankToNull('serial_number'),
            'estimated_cost' => $this->blankToNull('estimated_cost'),
            'estimated_delivery_at' => $this->blankToNull('estimated_delivery_at'),
        ]);
    }

    private function customerAlreadyExists(): bool
    {
        $email = $this->input('customer_email');
        $user = $this->user();

        if (! is_string($email) || $email === '' || $user === null) {
            return false;
        }

        return Customer::query()
            ->where('user_id', $user->id)
            ->where('email', $email)
            ->exists();
    }

    private function blankToNull(string $key): mixed
    {
        $value = $this->input($key);

        return $value === '' ? null : $value;
    }
}
