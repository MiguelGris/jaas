<?php

namespace App\Services;

use App\Models\CashClosing;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CashService
{
    /**
     * Calculate the live cash balance from the last monthly closing plus all
     * movements registered after that closed period.
     *
     * @return array{balance: float, opening_balance: float, income: float, expense: float, last_closing: ?CashClosing, starts_on: ?Carbon}
     */
    public function currentBalance(): array
    {
        $lastClosing = $this->latestClosing();
        $startsOn = $lastClosing === null ? null : $this->periodStart($lastClosing->year, $lastClosing->month)->addMonth();
        $endsAt = now();
        $openingBalance = (float) ($lastClosing?->balance ?? 0);
        $income = $this->incomeTotal($startsOn, $endsAt);
        $expense = $this->expenseTotal($startsOn, $endsAt);

        return [
            'balance' => round($openingBalance + $income - $expense, 2),
            'opening_balance' => $openingBalance,
            'income' => $income,
            'expense' => $expense,
            'last_closing' => $lastClosing,
            'starts_on' => $startsOn,
        ];
    }

    /**
     * @return array{year: int, month: int, starts_on: ?Carbon, ends_on: Carbon, previous_closing: ?CashClosing, previous_balance: float, total_income: float, total_expense: float, balance: float}
     */
    public function preview(int $year, int $month, bool $lock = false): array
    {
        $periodStart = $this->periodStart($year, $month);
        $periodEnd = $periodStart->copy()->endOfMonth();

        if ($periodStart->gte(now()->startOfMonth())) {
            throw ValidationException::withMessages([
                'period' => 'Solo se puede cerrar un mes que ya haya concluido.',
            ]);
        }

        $latestQuery = CashClosing::query()
            ->orderByDesc('year')
            ->orderByDesc('month');

        if ($lock) {
            $latestQuery->lockForUpdate();
        }

        $previousClosing = $latestQuery->first();

        if ($previousClosing !== null && $this->periodNumber($year, $month) <= $this->periodNumber($previousClosing->year, $previousClosing->month)) {
            throw ValidationException::withMessages([
                'period' => 'El periodo debe ser posterior al último cierre registrado.',
            ]);
        }

        $startsOn = $previousClosing === null
            ? null
            : $this->periodStart($previousClosing->year, $previousClosing->month)->addMonth();
        $previousBalance = (float) ($previousClosing?->balance ?? 0);
        $totalIncome = $this->incomeTotal($startsOn, $periodEnd);
        $totalExpense = $this->expenseTotal($startsOn, $periodEnd);

        return [
            'year' => $year,
            'month' => $month,
            'starts_on' => $startsOn,
            'ends_on' => $periodEnd,
            'previous_closing' => $previousClosing,
            'previous_balance' => $previousBalance,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'balance' => round($previousBalance + $totalIncome - $totalExpense, 2),
        ];
    }

    public function close(int $year, int $month, User $user): CashClosing
    {
        return DB::transaction(function () use ($year, $month, $user): CashClosing {
            $summary = $this->preview($year, $month, true);

            return CashClosing::query()->create([
                'year' => $year,
                'month' => $month,
                'total_income' => $summary['total_income'],
                'total_expense' => $summary['total_expense'],
                'balance' => $summary['balance'],
                'user_id' => $user->getKey(),
                'closed_at' => now(),
            ]);
        }, 3);
    }

    public function latestClosing(): ?CashClosing
    {
        return CashClosing::query()
            ->with('user')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();
    }

    public function ensureMovementDateIsOpen(DateTimeInterface|string $date, string $field): void
    {
        $lastClosing = $this->latestClosing();

        if ($lastClosing === null) {
            return;
        }

        $closedThrough = $this->periodStart($lastClosing->year, $lastClosing->month)->endOfMonth();

        if (Carbon::parse($date)->startOfDay()->lte($closedThrough)) {
            throw ValidationException::withMessages([
                $field => 'No se puede modificar un movimiento de un periodo que ya fue cerrado.',
            ]);
        }
    }

    private function incomeTotal(?Carbon $startsOn, Carbon $endsAt): float
    {
        $payments = Payment::query()->active()->where('paid_at', '<=', $endsAt);
        $incomes = Income::query()->whereDate('received_on', '<=', $endsAt->toDateString());

        if ($startsOn !== null) {
            $payments->where('paid_at', '>=', $startsOn->copy()->startOfDay());
            $incomes->whereDate('received_on', '>=', $startsOn->toDateString());
        }

        return round((float) $payments->sum('amount') + (float) $incomes->sum('amount'), 2);
    }

    private function expenseTotal(?Carbon $startsOn, Carbon $endsAt): float
    {
        $expenses = Expense::query()->whereDate('incurred_on', '<=', $endsAt->toDateString());

        if ($startsOn !== null) {
            $expenses->whereDate('incurred_on', '>=', $startsOn->toDateString());
        }

        return round((float) $expenses->sum('amount'), 2);
    }

    private function periodStart(int|string $year, int|string $month): Carbon
    {
        return Carbon::create((int) $year, (int) $month, 1)->startOfMonth();
    }

    private function periodNumber(int|string $year, int|string $month): int
    {
        return ((int) $year * 12) + (int) $month;
    }
}
