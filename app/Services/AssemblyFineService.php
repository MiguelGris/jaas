<?php

namespace App\Services;

use App\Models\Assembly;
use App\Models\AssemblyAttendance;
use App\Models\Customer;
use App\Models\Fine;
use App\Models\User;

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
            ->with(['assembly', 'customer.customerStatus'])
            ->eachById(function (AssemblyAttendance $attendance) use (&$created): void {
                if ($this->synchroniseAttendanceFine($attendance)) {
                    $created++;
                }
            });

        return $created;
    }

    /**
     * Keep the absence fine aligned with the attendance state without changing
     * payments or their allocations. Returns true only when a fine is created.
     */
    public function synchroniseAttendanceFine(AssemblyAttendance $attendance): bool
    {
        $attendance->loadMissing(['assembly', 'customer.customerStatus']);
        $assembly = $attendance->assembly;
        $customerStatus = strtoupper((string) $attendance->customer?->customerStatus?->name);

        $mustHaveFine = ! $attendance->attended
            && $assembly?->status === 'HELD'
            && (float) $assembly->absence_fine > 0
            && in_array($customerStatus, ['ACTIVE', 'ACTIVO'], true);

        if (! $mustHaveFine) {
            $this->removeOrCancelFine($attendance);

            return false;
        }

        $fine = Fine::query()->firstOrNew([
            'customer_id' => $attendance->customer_id,
            'assembly_id' => $attendance->assembly_id,
        ]);
        $isNew = ! $fine->exists;
        $audit = app(AuditService::class);
        $before = $isNew ? null : $audit->snapshot($fine);
        $paid = $isNew ? 0.0 : (float) $fine->paymentAllocations()->sum('amount');

        $fine->fill([
            'reason' => 'Inasistencia a la asamblea '.$assembly->assembly_code,
            'amount' => $assembly->absence_fine,
            'generated_on' => $fine->generated_on ?? now()->toDateString(),
            'status' => $paid >= (float) $assembly->absence_fine ? 'PAID' : 'PENDING',
        ]);
        $fine->save();

        if ($isNew) {
            $audit->created($this->actor(), $fine);
        } elseif ($before !== null) {
            $audit->updated($this->actor(), $fine, $before);
        }

        return $isNew;
    }

    private function removeOrCancelFine(AssemblyAttendance $attendance): void
    {
        $fine = Fine::query()
            ->where('customer_id', $attendance->customer_id)
            ->where('assembly_id', $attendance->assembly_id)
            ->first();

        if ($fine === null) {
            return;
        }

        $audit = app(AuditService::class);
        $before = $audit->snapshot($fine);

        if ($fine->paymentAllocations()->exists()) {
            if ($fine->status !== 'CANCELLED') {
                $fine->status = 'CANCELLED';
                $fine->save();
                $audit->updated($this->actor(), $fine, $before);
            }

            return;
        }

        $fine->delete();
        $audit->deleted($this->actor(), $fine, $before);
    }

    private function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
