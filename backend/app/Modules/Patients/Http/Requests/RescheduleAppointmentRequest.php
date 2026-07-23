<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Requests;

use App\Http\Middleware\AssignRequestId;
use App\Modules\Patients\Http\Rules\StrongAppointmentVersionTag;
use App\Modules\Scheduling\Application\AppointmentVersionTag;
use App\Modules\Scheduling\Application\RescheduleAppointmentData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * Validate and normalize a patient appointment reschedule command.
 */
final class RescheduleAppointmentRequest extends FormRequest
{
    private const ALLOWED_INPUTS = ['slot_id', 'idempotency_key', 'if_match'];

    /**
     * Defer record authorization to patient-scoped application queries.
     *
     * The route is already protected by Sanctum; ownership is checked without
     * revealing whether a foreign appointment identifier exists.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'slot_id' => ['required', 'string', 'uuid:7'],
            'idempotency_key' => [
                'required',
                'string',
                'min:16',
                'max:128',
                'regex:/^[\x21-\x7E]+$/D',
            ],
            'if_match' => ['required', new StrongAppointmentVersionTag],
        ];
    }

    /**
     * Reject undeclared inputs to keep the command boundary fail-closed.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $unknownInputs = array_diff(array_keys($this->all()), self::ALLOWED_INPUTS);

            foreach ($unknownInputs as $input) {
                $validator->errors()->add($input, "The {$input} field is not allowed.");
            }
        }];
    }

    /**
     * Convert validated transport data into the application command DTO.
     */
    public function toData(): RescheduleAppointmentData
    {
        $requestPublicId = $this->attributes->get(AssignRequestId::ATTRIBUTE);
        $version = AppointmentVersionTag::parse((string) $this->validated('if_match'));

        if (! is_string($requestPublicId) || $version === null) {
            throw new LogicException('A validated appointment reschedule requires request and version identifiers.');
        }

        return new RescheduleAppointmentData(
            slotId: (string) $this->validated('slot_id'),
            expectedVersion: $version,
            idempotencyKey: (string) $this->validated('idempotency_key'),
            requestPublicId: $requestPublicId,
        );
    }

    /**
     * Merge concurrency and idempotency headers into Laravel validation data.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
            'if_match' => $this->header('If-Match'),
        ]);
    }
}
