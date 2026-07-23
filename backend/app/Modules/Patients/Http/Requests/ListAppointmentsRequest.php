<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate bounded pagination and lifecycle filters for appointment listings.
 */
final class ListAppointmentsRequest extends FormRequest
{
    /**
     * Defer patient authorization to the authenticated portal boundary.
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
            'scope' => ['sometimes', 'string', 'in:upcoming,past,all'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Return the validated lifecycle scope with its safe default.
     */
    public function appointmentScope(): string
    {
        return (string) $this->validated('scope', 'upcoming');
    }

    /**
     * Return the validated and server-bounded page size.
     */
    public function perPage(): int
    {
        return (int) $this->validated('per_page', 20);
    }
}
