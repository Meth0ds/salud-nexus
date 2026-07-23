<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Documents\DocumentsServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Medication\MedicationServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Organizations\OrganizationsServiceProvider;
use App\Modules\Patients\PatientsServiceProvider;
use App\Modules\Privacy\PrivacyServiceProvider;
use App\Modules\Scheduling\SchedulingServiceProvider;
use Illuminate\Support\ServiceProvider;
use Tests\TestCase;

final class ModuleProvidersTest extends TestCase
{
    public function test_initial_module_boundaries_are_registered(): void
    {
        $providers = [
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

        foreach ($providers as $provider) {
            self::assertInstanceOf(ServiceProvider::class, $this->app->getProvider($provider), $provider);
        }
    }
}
