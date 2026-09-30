<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assembly;
use App\Models\AssemblyAttendance;
use App\Models\AssemblyType;
use App\Models\AuditLog;
use App\Models\BillingPeriod;
use App\Models\CashClosing;
use App\Models\Connection;
use App\Models\ConnectionStatus;
use App\Models\ConnectionType;
use App\Models\ConnectionUsageType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Fine;
use App\Models\Income;
use App\Models\IncomeType;
use App\Models\Invoice;
use App\Models\LateFeeSetting;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Neighborhood;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Property;
use App\Models\Rate;
use App\Models\Role;
use App\Models\Setting;
use App\Models\UsageType;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CashService;
use App\Services\MeterReadingService;
use App\Services\RateVersionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Controlador REST genérico para los recursos de la JASS.
 *
 * Cada recurso declara expresamente su modelo, validaciones y relaciones en
 * resourceDefinitions(). Esto mantiene respuestas consistentes y evita aceptar
 * atributos que no pertenecen al modelo solicitado.
 */
final class JassResourceController extends Controller
{
    private const AUTOMATED_RESOURCES = ['invoices', 'payments', 'fines'];

    /**
     * Return the names that must be registered with Route::apiResource().
     *
     * @return list<string>
     */
    public static function resourceNames(): array
    {
        return array_keys(self::resourceDefinitions());
    }

    public function index(Request $request)
    {
        $definition = $this->definition($request);
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        return $this->query($definition)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function store(Request $request, AuditService $audit): JsonResponse
    {
        $this->ensureAutomatedResourceIsReadOnly($request);
        $resource = $this->resourceName($request);
        $definition = $this->definition($request);
        $attributes = $request->validate($this->rules($definition));
        $this->assertCompoundUnique($definition, $attributes);
        $this->ensureCashMovementIsOpen($resource, $attributes);

        if (in_array($resource, ['incomes', 'expenses'], true)) {
            $attributes['user_id'] = $request->user()?->getKey();
        }

        if ($resource === 'cash-closings') {
            $user = $request->user();
            abort_if($user === null, 403);
            $record = app(CashService::class)->close((int) $attributes['year'], (int) $attributes['month'], $user);
            $audit->created($user, $record);

            return response()->json($record->load($definition['with']), 201);
        }

        if ($resource === 'meters') {
            $this->ensureMeterCanBeAssigned($attributes);
        }

        $record = match ($resource) {
            'rates' => app(RateVersionService::class)->create($attributes, $request->user()),
            'meter-readings' => app(MeterReadingService::class)->create($attributes, $request->user()?->getKey()),
            default => $this->modelClass($definition)::create($attributes),
        };
        if ($resource !== 'rates') {
            $audit->created($request->user(), $record);
        }

        return response()->json($record->load($definition['with']), 201);
    }

    public function show(Request $request, string $record)
    {
        return $this->find($this->definition($request), $record);
    }

    public function update(Request $request, string $record, AuditService $audit)
    {
        $this->ensureAutomatedResourceIsReadOnly($request);
        $resource = $this->resourceName($request);
        abort_if($resource === 'cash-closings', 405, 'Los cierres de caja confirmados no se pueden editar.');
        $definition = $this->definition($request);
        $model = $this->find($definition, $record);
        $attributes = $request->validate($this->updateRules($this->rules($definition, $record)));
        $this->ensureCashMovementIsOpen($resource, $attributes, $model);
        $this->assertCompoundUnique($definition, $attributes, $model);
        $before = $audit->snapshot($model);

        if ($resource === 'users' && $model->is($request->user()) && array_key_exists('active', $attributes) && ! $attributes['active']) {
            throw ValidationException::withMessages([
                'active' => 'No puedes desactivar tu propia cuenta.',
            ]);
        }

        if ($resource === 'connections') {
            $this->ensureConnectionCanUsePaymentMode($model, $attributes);
        }
        if ($resource === 'meters') {
            $this->ensureMeterCanBeAssigned($attributes, $model);
        }

        $passwordChanged = $resource === 'users' && array_key_exists('password', $attributes);

        if ($resource === 'rates') {
            $model = app(RateVersionService::class)->revise($model, $attributes, $request->user());
        } elseif ($resource === 'meter-readings') {
            $readingAttributes = array_merge(
                $model->only(['meter_id', 'read_on', 'current_reading', 'user_id', 'notes']),
                $attributes,
            );
            $model = app(MeterReadingService::class)->update($model, $readingAttributes);
        } else {
            $model->fill($attributes);
            $model->save();

            if ($resource === 'meters') {
                app(MeterReadingService::class)->recalculateForMeter($model);
            }
        }
        if ($resource !== 'rates') {
            $audit->updated($request->user(), $model, $before);
        }
        if ($passwordChanged) {
            $audit->passwordChanged($request->user(), $model);
        }

        return $model->fresh($definition['with']);
    }

    public function destroy(Request $request, string $record, AuditService $audit): JsonResponse
    {
        $this->ensureAutomatedResourceIsReadOnly($request);
        $resource = $this->resourceName($request);
        abort_if($resource === 'cash-closings', 405, 'Los cierres de caja confirmados no se pueden eliminar.');
        $model = $this->find($this->definition($request), $record);
        abort_if($resource === 'rates', 405, 'Las tarifas forman parte del historial y no se pueden eliminar.');
        $this->ensureCashMovementIsOpen($resource, [], $model);
        $before = $audit->snapshot($model);

        if ($resource === 'users' && $model->is($request->user())) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta.'], 422);
        }

        if ($resource === 'assembly-attendances') {
            $model->attended = false;
            $model->save();
            $audit->updated($request->user(), $model, $before);

            return response()->json(null, 204);
        }

        try {
            if ($resource === 'meter-readings') {
                app(MeterReadingService::class)->delete($model);
            } else {
                $model->delete();
            }
        } catch (QueryException) {
            return response()->json([
                'message' => 'No se puede eliminar el registro porque tiene información relacionada.',
            ], 409);
        }
        $audit->deleted($request->user(), $model, $before);

        return response()->json(null, 204);
    }

    /** @param array<string, mixed> $attributes */
    private function ensureCashMovementIsOpen(?string $resource, array $attributes, ?Model $model = null): void
    {
        $dateField = match ($resource) {
            'incomes' => 'received_on',
            'expenses' => 'incurred_on',
            default => null,
        };

        if ($dateField === null) {
            return;
        }

        $cash = app(CashService::class);

        if ($model !== null) {
            $cash->ensureMovementDateIsOpen($model->getAttribute($dateField), $dateField);
        }

        if (array_key_exists($dateField, $attributes)) {
            $cash->ensureMovementDateIsOpen($attributes[$dateField], $dateField);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function ensureConnectionCanUsePaymentMode(Connection $connection, array $attributes): void
    {
        $mode = $attributes['payment_mode'] ?? $connection->payment_mode ?? Connection::PAYMENT_FIXED;

        if ($mode === Connection::PAYMENT_FIXED && $connection->meters()->where('active', true)->exists()) {
            throw ValidationException::withMessages([
                'payment_mode' => 'Desactiva o retira el medidor activo antes de cambiar la conexión a pago fijo.',
            ]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function ensureMeterCanBeAssigned(array $attributes, ?Meter $meter = null): void
    {
        $connectionId = (int) ($attributes['connection_id'] ?? $meter?->connection_id);
        $connection = Connection::query()->findOrFail($connectionId);

        if ($connection->payment_mode !== Connection::PAYMENT_METERED) {
            throw ValidationException::withMessages([
                'connection_id' => 'Solo las conexiones configuradas con cobro por medidor pueden tener un medidor asignado.',
            ]);
        }

        $active = array_key_exists('active', $attributes) ? (bool) $attributes['active'] : ($meter?->active ?? true);
        if ($active) {
            $activeMeters = Meter::query()
                ->where('connection_id', $connection->getKey())
                ->where('active', true)
                ->when($meter, fn ($query) => $query->where('id', '!=', $meter->getKey()));

            if ($activeMeters->exists()) {
                throw ValidationException::withMessages(['connection_id' => 'La conexión ya tiene un medidor activo.']);
            }
        }

        $initialReading = (float) ($attributes['initial_reading'] ?? $meter?->initial_reading ?? 0);
        $firstReading = $meter?->readings()->orderBy('read_on')->orderBy('id')->first();
        if ($firstReading !== null && $initialReading > (float) $firstReading->current_reading) {
            throw ValidationException::withMessages([
                'initial_reading' => 'La lectura inicial no puede superar la primera lectura registrada.',
            ]);
        }
    }

    /**
     * @param  array{model: class-string<Model>, rules: callable(?string): array, with: list<string>}  $definition
     */
    private function find(array $definition, string $id): Model
    {
        return $this->query($definition)->findOrFail($id);
    }

    /**
     * @param  array{model: class-string<Model>, rules: callable(?string): array, with: list<string>}  $definition
     */
    private function query(array $definition)
    {
        return $this->modelClass($definition)::query()->with($definition['with']);
    }

    /**
     * @param  array{model: class-string<Model>, rules: callable(?string): array, with: list<string>}  $definition
     * @return class-string<Model>
     */
    private function modelClass(array $definition): string
    {
        return $definition['model'];
    }

    /**
     * @param  array{model: class-string<Model>, rules: callable(?string): array, with: list<string>}  $definition
     * @return array<string, string|list<string>>
     */
    private function rules(array $definition, ?string $id = null): array
    {
        $rules = $definition['rules'];

        return $rules($id);
    }

    /**
     * Make every rule optional for PATCH/PUT while retaining constraints when
     * the field is present in the request.
     *
     * @param  array<string, string|list<string>>  $rules
     * @return array<string, string|list<string>>
     */
    private function updateRules(array $rules): array
    {
        foreach ($rules as $field => $fieldRules) {
            $rules[$field] = is_array($fieldRules)
                ? ['sometimes', ...$fieldRules]
                : 'sometimes|'.$fieldRules;
        }

        return $rules;
    }

    /**
     * Resolve the resource from the generated route name (for example,
     * "v1.customers.show"). The resource registry is the allow-list used by
     * the controller, so no client-controlled class or table name is used.
     *
     * @return array{model: class-string<Model>, rules: callable(?string): array, with: list<string>}
     */
    private function definition(Request $request): array
    {
        $resource = $this->resourceName($request);
        $definitions = self::resourceDefinitions();

        abort_unless(is_string($resource) && array_key_exists($resource, $definitions), 404);

        return $definitions[$resource];
    }

    private function resourceName(Request $request): ?string
    {
        $segments = explode('.', (string) $request->route()?->getName());
        $resource = $segments[count($segments) - 2] ?? null;

        return is_string($resource) ? $resource : null;
    }

    private function ensureAutomatedResourceIsReadOnly(Request $request): void
    {
        abort_if(
            in_array($this->resourceName($request), self::AUTOMATED_RESOURCES, true),
            405,
            'Este recurso se genera mediante el flujo de cobranza automático.',
        );
    }

    private static function unique(string $table, string $column, ?string $id): string
    {
        return $id === null
            ? "unique:{$table},{$column}"
            : "unique:{$table},{$column},{$id}";
    }

    /**
     * Check database-level compound unique constraints before writing. This
     * also covers a partial update that changes only one member of a pair.
     *
     * @param  array{model: class-string<Model>, compound_unique?: list<list<string>>}  $definition
     * @param  array<string, mixed>  $attributes
     */
    private function assertCompoundUnique(array $definition, array $attributes, ?Model $current = null): void
    {
        foreach ($definition['compound_unique'] ?? [] as $fields) {
            $values = [];

            foreach ($fields as $field) {
                $value = array_key_exists($field, $attributes)
                    ? $attributes[$field]
                    : $current?->getAttribute($field);

                // SQL unique indexes allow more than one row with a NULL value.
                if ($value === null) {
                    continue 2;
                }

                $values[$field] = $value;
            }

            $query = $this->modelClass($definition)::query()->where($values);

            if ($current !== null) {
                $query->where($current->getKeyName(), '!=', $current->getKey());
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    $fields[0] => ['La combinación de '.implode(' y ', $fields).' ya existe.'],
                ]);
            }
        }
    }

    /**
     * @return array<string, array{model: class-string<Model>, rules: callable(?string): array, with: list<string>}>
     */
    private static function resourceDefinitions(): array
    {
        return [
            'roles' => [
                'model' => Role::class,
                'rules' => static fn (?string $id): array => [
                    'name' => ['required', 'string', 'max:50', self::unique('roles', 'name', $id)],
                    'description' => 'nullable|string|max:255',
                ],
                'with' => ['permissions'],
            ],
            'permissions' => [
                'model' => Permission::class,
                'rules' => static fn (?string $id): array => [
                    'name' => ['required', 'string', 'max:100', self::unique('permissions', 'name', $id)],
                    'description' => 'nullable|string|max:255',
                ],
                'with' => ['roles'],
            ],
            'users' => [
                'model' => User::class,
                'rules' => static fn (?string $id): array => [
                    'name' => 'required|string|max:100',
                    'last_name' => 'nullable|string|max:100',
                    'email' => ['required', 'email', 'max:150', self::unique('users', 'email', $id)],
                    'password' => $id === null ? 'required|string|min:8' : 'string|min:8',
                    'role_id' => 'required|integer|exists:roles,id',
                    'active' => 'required|boolean',
                ],
                'with' => ['role'],
            ],
            'settings' => [
                'model' => Setting::class,
                'rules' => static fn (?string $id): array => [
                    'key' => ['required', 'string', 'max:100', self::unique('settings', 'key', $id)],
                    'value' => 'required|string|max:255',
                    'description' => 'nullable|string|max:255',
                ],
                'with' => [],
            ],
            'customer-statuses' => [
                'model' => CustomerStatus::class,
                'rules' => static fn (?string $id): array => [
                    'name' => ['required', 'string', 'max:50', self::unique('customer_statuses', 'name', $id)],
                    'description' => 'nullable|string|max:255',
                ],
                'with' => [],
            ],
            'neighborhoods' => [
                'model' => Neighborhood::class,
                'rules' => static fn (?string $id): array => [
                    'name' => ['required', 'string', 'max:100', self::unique('neighborhoods', 'name', $id)],
                    'description' => 'nullable|string|max:255',
                    'active' => 'required|boolean',
                ],
                'with' => [],
            ],
            'connection-types' => [
                'model' => ConnectionType::class,
                'rules' => static fn (?string $id): array => [
                    'name' => ['required', 'string', 'max:50', self::unique('connection_types', 'name', $id)],
                    'description' => 'nullable|string|max:200',
                ],
                'with' => [],
            ],
            'connection-statuses' => [
                'model' => ConnectionStatus::class,
                'rules' => static fn (?string $id): array => [
                    'name' => ['required', 'string', 'max:50', self::unique('connection_statuses', 'name', $id)],
                    'description' => 'nullable|string|max:200',
                ],
                'with' => [],
            ],
            'usage-types' => [
                'model' => UsageType::class,
                'rules' => static fn (?string $id): array => [
                    'name' => ['required', 'string', 'max:50', self::unique('usage_types', 'name', $id)],
                    'description' => 'nullable|string|max:200',
                ],
                'with' => [],
            ],
            'billing-periods' => [
                'model' => BillingPeriod::class,
                'rules' => static fn (?string $id): array => [
                    'months' => 'required|integer|min:1|max:120',
                    'description' => 'nullable|string|max:100',
                ],
                'with' => [],
            ],
            'late-fee-settings' => [
                'model' => LateFeeSetting::class,
                'rules' => static fn (?string $id): array => [
                    'monthly_amount' => 'required|numeric|min:0',
                    'grace_months' => 'required|integer|min:1|max:12',
                    'starts_on' => 'required|date',
                    'ends_on' => 'nullable|date|after_or_equal:starts_on',
                ],
                'with' => [],
            ],
            'payment-methods' => [
                'model' => PaymentMethod::class,
                'rules' => static fn (?string $id): array => [
                    'name' => ['required', 'string', 'max:50', self::unique('payment_methods', 'name', $id)],
                ],
                'with' => [],
            ],
            'assembly-types' => [
                'model' => AssemblyType::class,
                'rules' => static fn (?string $id): array => [
                    'name' => ['required', 'string', 'max:50', self::unique('assembly_types', 'name', $id)],
                    'description' => 'nullable|string|max:200',
                ],
                'with' => [],
            ],
            'income-types' => [
                'model' => IncomeType::class,
                'rules' => static fn (?string $id): array => [
                    'name' => ['required', 'string', 'max:100', self::unique('income_types', 'name', $id)],
                ],
                'with' => [],
            ],
            'expense-categories' => [
                'model' => ExpenseCategory::class,
                'rules' => static fn (?string $id): array => [
                    'name' => ['required', 'string', 'max:100', self::unique('expense_categories', 'name', $id)],
                ],
                'with' => [],
            ],
            'customers' => [
                'model' => Customer::class,
                'rules' => static fn (?string $id): array => [
                    'national_id' => ['nullable', 'string', 'max:20', self::unique('customers', 'national_id', $id)],
                    'first_name' => 'required|string|max:100',
                    'last_name' => 'required|string|max:100',
                    'birth_date' => 'nullable|date',
                    'phone' => 'nullable|string|max:30',
                    'email' => 'nullable|email|max:150',
                    'address' => 'nullable|string|max:250',
                    'customer_status_id' => 'required|integer|exists:customer_statuses,id',
                    'registered_on' => 'required|date',
                    'notes' => 'nullable|string',
                ],
                'with' => ['customerStatus', 'properties.neighborhood'],
            ],
            'properties' => [
                'model' => Property::class,
                'rules' => static fn (?string $id): array => [
                    'customer_id' => 'required|integer|exists:customers,id',
                    'neighborhood_id' => 'required|integer|exists:neighborhoods,id',
                    'address' => 'required|string|max:250',
                    'reference' => 'nullable|string|max:250',
                    'property_code' => 'nullable|string|max:50',
                    'active' => 'required|boolean',
                ],
                'with' => ['customer', 'neighborhood', 'connections'],
            ],
            'connections' => [
                'model' => Connection::class,
                'rules' => static fn (?string $id): array => [
                    'property_id' => 'required|integer|exists:properties,id',
                    'payment_mode' => 'required|in:FIXED,METERED',
                    'connection_type_id' => 'required|integer|exists:connection_types,id',
                    'connection_status_id' => 'required|integer|exists:connection_statuses,id',
                    'installed_on' => 'nullable|date',
                    'notes' => 'nullable|string',
                ],
                'with' => ['property.customer', 'connectionType', 'connectionStatus', 'usageTypes', 'meters'],
            ],
            'connection-usage-types' => [
                'model' => ConnectionUsageType::class,
                'rules' => static fn (?string $id): array => [
                    'connection_id' => 'required|integer|exists:connections,id',
                    'usage_type_id' => 'required|integer|exists:usage_types,id',
                    'starts_on' => 'required|date',
                    'ends_on' => 'nullable|date|after_or_equal:starts_on',
                ],
                'with' => ['connection', 'usageType'],
            ],
            'rates' => [
                'model' => Rate::class,
                'rules' => static fn (?string $id): array => [
                    'usage_type_id' => 'required|integer|exists:usage_types,id',
                    'year' => 'required|integer|between:2000,2100',
                    'amount' => 'required|numeric|min:0',
                    'metered_unit_price' => 'nullable|numeric|min:0',
                    'starts_on' => 'required|date',
                    'ends_on' => 'nullable|date|after_or_equal:starts_on',
                    'approved_by_assembly' => 'required|boolean',
                    'notes' => 'nullable|string|max:250',
                ],
                'with' => ['usageType'],
            ],
            'invoices' => [
                'model' => Invoice::class,
                'rules' => static fn (?string $id): array => [
                    'connection_id' => 'required|integer|exists:connections,id',
                    'billing_period_id' => 'required|integer|exists:billing_periods,id',
                    'issued_on' => 'required|date',
                    'due_on' => 'required|date|after_or_equal:issued_on',
                    'period_starts_on' => 'required|date',
                    'period_ends_on' => 'required|date|after_or_equal:period_starts_on',
                    'rate' => 'required|numeric|min:0',
                    'late_fee' => 'required|numeric|min:0',
                    'fines' => 'required|numeric|min:0',
                    'total' => 'required|numeric|min:0',
                    'status' => 'required|in:PENDING,PAID,CANCELLED',
                ],
                'with' => ['connection.property.customer', 'billingPeriod', 'paymentAllocations'],
            ],
            'payments' => [
                'model' => Payment::class,
                'rules' => static fn (?string $id): array => [
                    'customer_id' => 'required|integer|exists:customers,id',
                    'invoice_id' => 'nullable|integer|exists:invoices,id',
                    'paid_at' => 'required|date',
                    'amount' => 'required|numeric|min:0.01',
                    'payment_method_id' => 'required|integer|exists:payment_methods,id',
                    'source' => 'required|in:COUNTER,WEB',
                    'user_id' => 'nullable|integer|exists:users,id',
                    'operation_number' => 'nullable|string|max:100',
                    'notes' => 'nullable|string|max:250',
                ],
                'with' => ['customer', 'invoice', 'paymentMethod', 'user', 'voidedBy', 'allocations'],
            ],
            'meters' => [
                'model' => Meter::class,
                'rules' => static fn (?string $id): array => [
                    'connection_id' => 'required|integer|exists:connections,id',
                    'meter_number' => ['required', 'string', 'max:50', self::unique('meters', 'meter_number', $id)],
                    'initial_reading' => 'required|numeric|min:0',
                    'installed_on' => 'nullable|date',
                    'active' => 'required|boolean',
                ],
                'with' => ['connection'],
            ],
            'meter-readings' => [
                'model' => MeterReading::class,
                'rules' => static fn (?string $id): array => [
                    'meter_id' => 'required|integer|exists:meters,id',
                    'read_on' => 'required|date',
                    'current_reading' => 'required|numeric|min:0',
                    'notes' => 'nullable|string|max:250',
                ],
                'with' => ['meter', 'user'],
            ],
            'assemblies' => [
                'model' => Assembly::class,
                'rules' => static fn (?string $id): array => [
                    'assembly_type_id' => 'required|integer|exists:assembly_types,id',
                    'held_on' => 'required|date',
                    'held_at' => 'nullable|date_format:H:i,H:i:s',
                    'place' => 'nullable|string|max:250',
                    'description' => 'nullable|string',
                    'absence_fine' => 'required|numeric|min:0',
                    'status' => 'required|in:SCHEDULED,HELD,CANCELLED',
                ],
                'with' => ['assemblyType', 'attendances.customer', 'fines.customer'],
            ],
            'assembly-attendances' => [
                'model' => AssemblyAttendance::class,
                'rules' => static fn (?string $id): array => [
                    'assembly_id' => 'required|integer|exists:assemblies,id',
                    'customer_id' => 'required|integer|exists:customers,id',
                    'attended' => 'required|boolean',
                    'notes' => 'nullable|string|max:250',
                ],
                'with' => ['assembly', 'customer'],
                'compound_unique' => [['assembly_id', 'customer_id']],
            ],
            'fines' => [
                'model' => Fine::class,
                'rules' => static fn (?string $id): array => [
                    'customer_id' => 'required|integer|exists:customers,id',
                    'assembly_id' => 'required|integer|exists:assemblies,id',
                    'reason' => 'nullable|string|max:250',
                    'amount' => 'required|numeric|min:0.01',
                    'generated_on' => 'required|date',
                    'status' => 'required|in:PENDING,PAID,CANCELLED',
                ],
                'with' => ['customer', 'assembly'],
                'compound_unique' => [['customer_id', 'assembly_id']],
            ],
            'incomes' => [
                'model' => Income::class,
                'rules' => static fn (?string $id): array => [
                    'income_type_id' => 'required|integer|exists:income_types,id',
                    'received_on' => 'required|date',
                    'concept' => 'nullable|string|max:250',
                    'amount' => 'required|numeric|min:0.01',
                    'reference' => 'nullable|string|max:100',
                ],
                'with' => ['incomeType', 'user'],
            ],
            'expenses' => [
                'model' => Expense::class,
                'rules' => static fn (?string $id): array => [
                    'expense_category_id' => 'required|integer|exists:expense_categories,id',
                    'incurred_on' => 'required|date',
                    'concept' => 'nullable|string|max:250',
                    'amount' => 'required|numeric|min:0.01',
                    'receipt' => 'nullable|string|max:100',
                ],
                'with' => ['expenseCategory', 'user'],
            ],
            'cash-closings' => [
                'model' => CashClosing::class,
                'rules' => static fn (?string $id): array => [
                    'year' => 'required|integer|between:2000,2100',
                    'month' => 'required|integer|between:1,12',
                ],
                'with' => ['user'],
                'compound_unique' => [['year', 'month']],
            ],
            'audit-logs' => [
                'model' => AuditLog::class,
                'rules' => static fn (?string $id): array => [],
                'with' => ['user'],
            ],
        ];
    }
}
