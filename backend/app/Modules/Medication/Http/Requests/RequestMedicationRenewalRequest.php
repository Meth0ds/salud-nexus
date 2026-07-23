<?php

declare(strict_types=1);

namespace App\Modules\Medication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validate a medication renewal command that carries no request body.
 */
final class RequestMedicationRenewalRequest extends FormRequest
{
    /**
     * Defer medication authorization to the patient-scoped application query.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => [
                'required',
                'string',
                'min:16',
                'max:128',
                'regex:/^[\x21-\x7E]+$/D',
            ],
        ];
    }

    /**
     * Return the validated idempotency key.
     */
    public function idempotencyKey(): string
    {
        return (string) $this->validated('idempotency_key');
    }

    /**
     * Reject body fields because the route identifier defines the command.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $bodyFields = array_diff(array_keys($this->json()->all()), ['idempotency_key']);

            if ($bodyFields !== []) {
                $validator->errors()->add('request', 'This operation does not accept body fields.');
            }
        });
    }

    /**
     * Merge the idempotency header into Laravel validation data.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }
}
