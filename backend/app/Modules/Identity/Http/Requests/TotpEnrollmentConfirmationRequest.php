<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validate a fail-closed TOTP enrollment confirmation command.
 */
final class TotpEnrollmentConfirmationRequest extends FormRequest
{
    private const ALLOWED_INPUTS = ['code'];

    /**
     * Allow authenticated requests to reach the application assurance checks.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the strict six-digit TOTP validation rules.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^\d{6}$/D'],
        ];
    }

    /**
     * Reject undeclared inputs to keep the confirmation boundary fail-closed.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $unknownInputs = array_diff(array_keys($this->json()->all()), self::ALLOWED_INPUTS);

            foreach ($unknownInputs as $input) {
                $validator->errors()->add($input, "The {$input} field is not allowed.");
            }
        }];
    }

    /**
     * Return the validated TOTP for immediate in-memory verification.
     */
    public function code(): string
    {
        return (string) $this->validated('code');
    }
}
