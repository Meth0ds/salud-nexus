<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validate that an MFA command carries no undeclared JSON fields.
 */
final class EmptyMfaRequest extends FormRequest
{
    /**
     * Allow authenticated requests to reach the application assurance checks.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return the empty validation rule set for this command.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Reject every JSON field to keep the command boundary fail-closed.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->json()->all()) as $input) {
                $validator->errors()->add($input, "The {$input} field is not allowed.");
            }
        }];
    }
}
