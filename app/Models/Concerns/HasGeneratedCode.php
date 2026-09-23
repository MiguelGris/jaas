<?php

namespace App\Models\Concerns;

use App\Support\Codes\CodeGenerator;
use Illuminate\Database\Eloquent\Model;

trait HasGeneratedCode
{
    protected static function bootHasGeneratedCode(): void
    {
        static::creating(function (Model $model): void {
            $definition = $model->generatedCodeDefinition();
            $field = $definition['field'];

            if ($model->getAttribute($field) !== null) {
                return;
            }

            $model->setAttribute(
                $field,
                CodeGenerator::next($definition, $model->getAttribute($definition['date_field'] ?? ''))
            );
        });
    }

    /**
     * @return array{field: string, series: string, prefix: string, padding: int, date_field?: string}
     */
    abstract protected function generatedCodeDefinition(): array;
}
