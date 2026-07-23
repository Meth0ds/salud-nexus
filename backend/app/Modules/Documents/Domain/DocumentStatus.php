<?php

declare(strict_types=1);

namespace App\Modules\Documents\Domain;

/**
 * Represent the publication lifecycle of a clinical document.
 */
enum DocumentStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Withdrawn = 'withdrawn';
}
