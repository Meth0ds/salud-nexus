<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Requests;

use App\Modules\Scheduling\Application\BookAppointmentData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate and normalize a patient appointment booking command.
 */
final class BookAppointmentRequest extends FormRequest
{
    /**
     * Defer ownership checks to the patient-scoped application service.
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
            'appointment_type_id' => ['required', 'string', 'uuid:7'],
            'slot_id' => ['required', 'string', 'uuid:7'],
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
     * Convert validated transport data into the application command DTO.
     */
    public function toData(): BookAppointmentData
    {
        return new BookAppointmentData(
            appointmentTypeId: (string) $this->validated('appointment_type_id'),
            slotId: (string) $this->validated('slot_id'),
            idempotencyKey: (string) $this->validated('idempotency_key'),
        );
    }

    /**
     * Merge the idempotency header into Laravel validation data.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }
}
