<?php

declare(strict_types=1);

return [
    // Operational policy remains server-side so clients cannot authorize a late change.
    'patient_change_cutoff_minutes' => (int) env('PATIENT_APPOINTMENT_CHANGE_CUTOFF_MINUTES', 120),
];
