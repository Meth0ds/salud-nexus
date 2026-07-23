<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Organizations\Domain\CenterStatus;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Scheduling\Domain\SlotStatus;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentType;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\Clock\ClockInterface;

/**
 * Query bounded appointment types with future, unallocated slots.
 */
final readonly class GetBookingOptions
{
    public function __construct(private ClockInterface $clock) {}

    /**
     * Get booking options available to the patient's organization and center.
     *
     * @return Collection<int, AppointmentType>
     */
    public function handle(Patient $patient): Collection
    {
        $now = $this->clock->now();

        return AppointmentType::query()
            ->where('organization_id', $patient->organization_id)
            ->where('is_active', true)
            ->whereHas('healthService', static fn ($query) => $query->where('is_active', true))
            ->whereHas('slots', static function ($query) use ($now): void {
                $query->where('status', SlotStatus::Open->value)
                    ->where('starts_at', '>=', $now)
                    ->whereDoesntHave('activeAllocation')
                    ->whereHas('center', static fn ($centerQuery) => $centerQuery->where(
                        'status',
                        CenterStatus::Active->value,
                    ));
            })
            ->with([
                'healthService',
                'slots' => static function ($query) use ($now): void {
                    $query->where('status', SlotStatus::Open->value)
                        ->where('starts_at', '>=', $now)
                        ->whereDoesntHave('activeAllocation')
                        ->whereHas('center', static fn ($centerQuery) => $centerQuery->where(
                            'status',
                            CenterStatus::Active->value,
                        ))
                        ->with('center')
                        ->orderBy('starts_at')
                        ->limit(50);
                },
            ])
            ->orderBy('name')
            ->limit(30)
            ->get();
    }
}
