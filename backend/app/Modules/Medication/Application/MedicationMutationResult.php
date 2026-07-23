<?php

declare(strict_types=1);

namespace App\Modules\Medication\Application;

use Illuminate\Database\Eloquent\Model;

/**
 * Return a medication mutation resource with its idempotency replay state.
 *
 * @template TModel of Model
 */
final readonly class MedicationMutationResult
{
    /**
     * Store the Eloquent resource produced by the mutation.
     *
     * @var TModel
     */
    public Model $resource;

    /**
     * Create a mutation result for the persisted model.
     *
     * @param  TModel  $resource
     */
    public function __construct(
        Model $resource,
        public bool $replayed,
    ) {
        $this->resource = $resource;
    }
}
