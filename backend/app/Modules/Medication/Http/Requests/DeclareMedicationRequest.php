<?php

declare(strict_types=1);

namespace App\Modules\Medication\Http\Requests;

use App\Modules\Medication\Application\DeclareMedicationData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validate and normalize a patient-provided medication declaration.
 */
final class DeclareMedicationRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:160', 'regex:/^[^\x00-\x1F\x7F]+$/uD'],
            'presentation' => ['nullable', 'string', 'max:120', 'regex:/^[^\x00-\x1F\x7F]+$/uD'],
            'schedule_label' => ['required', 'string', 'min:2', 'max:160', 'regex:/^[^\x00-\x1F\x7F]+$/uD'],
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
    public function toData(): DeclareMedicationData
    {
        $presentation = $this->validated('presentation');

        return new DeclareMedicationData(
            name: (string) $this->validated('name'),
            presentation: is_string($presentation) && $presentation !== '' ? $presentation : null,
            scheduleLabel: (string) $this->validated('schedule_label'),
            idempotencyKey: (string) $this->validated('idempotency_key'),
        );
    }

    /**
     * Reject undeclared JSON inputs to keep the command boundary fail-closed.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unknown = array_diff(
                array_keys($this->json()->all()),
                ['name', 'presentation', 'schedule_label', 'idempotency_key'],
            );

            if ($unknown !== []) {
                $validator->errors()->add('request', 'Unknown fields are not allowed.');
            }
        });
    }

    /**
     * Normalize human-entered text and merge the idempotency header.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $presentation = $this->input('presentation');
        $schedule = $this->input('schedule_label');
        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'presentation' => is_string($presentation) ? trim($presentation) : $presentation,
            'schedule_label' => is_string($schedule) ? trim($schedule) : $schedule,
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }
}
