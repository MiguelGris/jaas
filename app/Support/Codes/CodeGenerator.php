<?php

namespace App\Support\Codes;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CodeGenerator
{
    /**
     * @param array{series: string, prefix: string, padding: int, date_field?: string} $definition
     */
    public static function next(array $definition, mixed $date = null): string
    {
        $year = array_key_exists('date_field', $definition)
            ? self::date($date)->format('y')
            : null;
        $series = $definition['series'].($year === null ? '' : ':'.$year);
        $number = self::nextNumber($series);
        $sequence = str_pad((string) $number, $definition['padding'], '0', STR_PAD_LEFT);

        return $year === null
            ? $definition['prefix'].'_'.$sequence
            : $definition['prefix'].$year.'-'.$sequence;
    }

    private static function nextNumber(string $series): int
    {
        // The row lock ensures that two concurrent creations cannot receive
        // the same value. Gaps are acceptable when a later save is rolled back.
        return DB::transaction(function () use ($series): int {
            $row = DB::table('code_sequences')
                ->where('series', $series)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('code_sequences')->insert([
                    'series' => $series,
                    'last_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = (int) $row->last_value + 1;

            DB::table('code_sequences')
                ->where('id', $row->id)
                ->update(['last_value' => $next, 'updated_at' => now()]);

            return $next;
        }, 3);
    }

    private static function date(mixed $value): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return $value === null ? now() : Carbon::parse($value);
    }
}
