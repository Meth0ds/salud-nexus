<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Domain\MfaVerificationMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validate a fail-closed MFA challenge verification command.
 */
final class MfaChallengeVerificationRequest extends FormRequest
{
    private const ALLOWED_INPUTS = ['challenge_id', 'method', 'code'];

    /**
     * Allow guest sessions to submit their session-bound challenge.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the challenge verification validation rules.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string', 'uuid:7'],
            'method' => ['required', 'string', Rule::enum(MfaVerificationMethod::class)],
            'code' => ['required', 'string', 'min:6', 'max:64'],
        ];
    }

    /**
     * Reject undeclared fields and method-specific malformed codes.
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

            $method = $this->input('method');
            $code = $this->input('code');

            if (
                $method === MfaVerificationMethod::Totp->value
                && (! is_string($code) || preg_match('/^\d{6}$/D', $code) !== 1)
            ) {
                $validator->errors()->add('code', 'The authentication code format is invalid.');
            }

            if (
                $method === MfaVerificationMethod::Recovery->value
                && (! is_string($code) || preg_match('/^[A-Za-z0-9\s-]{23,64}$/D', $code) !== 1)
            ) {
                $validator->errors()->add('code', 'The authentication code format is invalid.');
            }
        }];
    }

    /**
     * Return the validated opaque challenge identifier.
     */
    public function challengeId(): string
    {
        return (string) $this->validated('challenge_id');
    }

    /**
     * Return the validated factor method.
     */
    public function verificationMethod(): MfaVerificationMethod
    {
        return MfaVerificationMethod::from((string) $this->validated('method'));
    }

    /**
     * Return the factor code for immediate in-memory verification.
     */
    public function code(): string
    {
        return (string) $this->validated('code');
    }
}
