<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validate and normalize credentials for password authentication.
 */
final class PasswordLoginRequest extends FormRequest
{
    /**
     * Allow the unauthenticated request to reach credential verification.
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
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'min:1', 'max:1024'],
        ];
    }

    /**
     * Return the validated canonical email address.
     */
    public function normalizedEmail(): string
    {
        return (string) $this->validated('email');
    }

    /**
     * Return the validated password for immediate authentication use.
     */
    public function password(): string
    {
        return (string) $this->validated('password');
    }

    /**
     * Trim and lowercase the email address before Laravel validation.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }
}
