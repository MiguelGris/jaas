<?php

namespace App\Services;

use App\Models\Assembly;
use App\Models\AssemblyAttendance;
use App\Models\Customer;
use App\Models\Fine;

final class AssemblyFineService
{
    public function prepareAttendance(Assembly $assembly): void
    {
        Customer::query()
            ->whereDate('registered_on', '<=', $assembly->held_on)
            ->whereHas('customerStatus', fn ($query) => $query->whereIn('name', ['ACTIVE', 'ACTIVO', 'EXEMPT', 'EXONERADO']))
            ->select('id')
            ->eachById(function (Customer $customer) use ($assembly): void {
                AssemblyAttendance::query()->firstOrCreate([
                    'assembly_id' => $assembly->getKey(),
                    'customer_id' => $customer->getKey(),
                ], ['attended' => false]);
            });
    }

    public function applyAbsenceFines(Assembly $assembly): int
    {
        if ((float) $assembly->absence_fine <= 0) {
            return 0;
        }

        $this->prepareAttendance($assembly);
        $created = 0;

        AssemblyAttendance::query()
            ->where('assembly_id', $assembly->getKey())
            ->where('attended', false)
            ->whereHas('customer.customerStatus', fn ($query) => $query->whereIn('name', ['ACTIVE', 'ACTIVO']))
            ->with('customer')
            ->eachById(function (AssemblyAttendance $attendance) use ($assembly, &$created): void {
                $fine = Fine::query()->firstOrCreate([
                    'customer_id' => $attendance->customer_id,
                    'assembly_id' => $assembly->getKey(),
                ], [
                    'reason' => 'Inasistencia a la asamblea '.$assembly->assembly_code,
                    'amount' => $assembly->absence_fine,
                    'generated_on' => now()->toDateString(),
                    'status' => 'PENDING',
                ]);

                if ($fine->wasRecentlyCreated) {
                    $created++;
                }
            });

        return $created;
    }
}
