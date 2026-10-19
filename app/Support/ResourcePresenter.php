<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

final class ResourcePresenter
{
    public static function value(string $resource, Model $record, string $name, array $field): mixed
    {
        $value = $record->getAttribute($name);
        if ($record instanceof Setting && $name === 'key') {
            $value = $record->displayName();
        }
        if ($record instanceof Setting && $name === 'description') {
            $value = $record->displayDescription();
        }
        if (isset($field['relation'])) {
            $related = $record->{$field['relation']};
            $value = $related instanceof User ? $related->display_name : data_get($related, $field['options']['label']);
        }
        if (isset($field['choices'])) {
            $value = $field['choices'][$value] ?? $value;
        } elseif (is_string($value)) {
            $value = CatalogLabel::value($value);
        }
        if ($resource === 'audit-logs' && $name === 'table_name') {
            $value = $record->moduleLabel();
        }
        if ($field['type'] === 'checkbox') {
            return $value ? 'Sí' : 'No';
        }
        if ($field['type'] === 'number' && $value !== null) {
            return $name === 'year' ? (string) (int) $value : number_format((float) $value, ($field['integer'] ?? false) ? 0 : 2);
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format($field['type'] === 'datetime-local' ? 'd/m/Y H:i' : 'd/m/Y');
        }

        return $value;
    }
}
