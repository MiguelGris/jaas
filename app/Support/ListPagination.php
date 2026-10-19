<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class ListPagination
{
    public const DEFAULT_SIZE = 25;

    public const SIZES = [25, 50, 100, 200];

    public static function rules(): array
    {
        return ['nullable', 'integer', Rule::in(self::SIZES)];
    }
}
