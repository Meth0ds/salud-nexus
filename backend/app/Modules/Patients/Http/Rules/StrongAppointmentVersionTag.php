<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Rules;

use App\Modules\Scheduling\Application\AppointmentVersionTag;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accept one strong, quoted appointment version and reject weak validators.
 */
final class StrongAppointmentVersionTag implements ValidationRule
{
    /**
     * Validate a canonical strong ETag suitable for optimistic concurrency.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || AppointmentVersionTag::parse($value) === null) {
            $fail('The :attribute field must contain one strong appointment ETag.');
        }
    }
}
