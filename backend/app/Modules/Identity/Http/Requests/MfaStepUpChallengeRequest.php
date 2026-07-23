<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Domain\MfaStepUpPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validate a purpose-bound MFA step-up challenge command.
 */
final class MfaStepUpChallengeRequest extends FormRequest
{
    private const ALLOWED_INPUTS = ['purpose'];

    /**
     * Allow the authenticated request to reach application assurance checks.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the allowlisted step-up purpose validation rules.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', Rule::enum(MfaStepUpPurpose::class)],
        ];
    }

    /**
     * Reject undeclared inputs to keep the step-up boundary fail-closed.
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
     * Return the validated allowlisted step-up purpose.
     */
    public function purpose(): MfaStepUpPurpose
    {
        return MfaStepUpPurpose::from((string) $this->validated('purpose'));
    }
}
