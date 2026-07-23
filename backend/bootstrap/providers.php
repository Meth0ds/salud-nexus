<?php

declare(strict_types=1);

use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Documents\DocumentsServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Medication\MedicationServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Organizations\OrganizationsServiceProvider;
use App\Modules\Patients\PatientsServiceProvider;
use App\Modules\Privacy\PrivacyServiceProvider;
use App\Modules\Scheduling\SchedulingServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    OrganizationsServiceProvider::class,
    PatientsServiceProvider::class,
    SchedulingServiceProvider::class,
    MedicationServiceProvider::class,
    DocumentsServiceProvider::class,
    PrivacyServiceProvider::class,
    AuditServiceProvider::class,
    NotificationsServiceProvider::class,
];
