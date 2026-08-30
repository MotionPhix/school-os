<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Events\FeeStructureToggled;
use App\Models\FeeStructure;

final class ToggleFeeStructure
{
    public function handle(FeeStructure $fee, bool $isActive): FeeStructure
    {
        if ($fee->is_active === $isActive) {
            return $fee;
        }
        $fee->is_active = $isActive;
        $fee->save();
        FeeStructureToggled::dispatch($fee);

        return $fee->refresh();
    }
}
