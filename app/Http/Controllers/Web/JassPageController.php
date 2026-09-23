<?php

namespace App\Http\Controllers\Web;

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
use App\Services\DebtService;
use App\Services\MeterReadingService;
use App\Services\ReportService;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Server-rendered management pages for the JASS domain.
 *
 * A resource registry keeps menus, fields, relation selectors and validation
 * in one place, so the same Blade templates can render every module.
 */
final class JassPageController extends Controller
{
    public function __construct()
    {
        view()->share('navigation', self::navigation());
    }

    /**
     * @return list<string>
     */
    public static function resourceSlugs(): array
    {
        return array_keys(self::definitions());
    }

    public function dashboard(ReportService $reports, DebtService $debts): View
    {
        $today = now();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();
        $monthlyCollection = (float) Payment::query()->whereBetween('paid_at', [$startOfMonth, $endOfMonth])->sum('amount');
        $monthlyExpenses = (float) Expense::query()->whereBetween('incurred_on', [$startOfMonth, $endOfMonth])->sum('amount');
        $debtors = $debts->debtors();
        $upcomingAssemblies = Assembly::query()
            ->where('status', 'SCHEDULED')
            ->whereDate('held_on', '>=', $today->toDateString())
            ->orderBy('held_on');
        $nextAssembly = (clone $upcomingAssemblies)->first();
        $upcomingAssemblyCount = $upcomingAssemblies->count();
        $dailyPaymentQuery = Payment::query()->whereDate('paid_at', $today->toDateString());
        $dailyPaymentCount = (clone $dailyPaymentQuery)->count();
        $dailyPaymentAmount = (float) $dailyPaymentQuery->sum('amount');

        $metrics = [
            ['label' => 'Clientes', 'model' => Customer::class, 'resource' => 'customers', 'accent' => 'sky'],
            ['label' => 'Conexiones', 'model' => Connection::class, 'resource' => 'connections', 'accent' => 'violet'],
            ['label' => 'Cuotas pendientes', 'model' => Invoice::class, 'resource' => 'invoices', 'accent' => 'amber', 'pending' => true],
            ['label' => 'Morosos', 'value' => $debtors->count(), 'detail' => 'S/ '.number_format((float) $debtors->sum('total'), 2).' por cobrar', 'resource' => 'invoices', 'accent' => 'amber'],
            ['label' => 'Próximas asambleas', 'value' => $upcomingAssemblyCount, 'detail' => $nextAssembly ? 'Próxima: '.$nextAssembly->held_on->format('d/m/Y') : 'No hay asambleas programadas', 'resource' => 'assemblies', 'accent' => 'violet'],
            ['label' => 'Pagos del día', 'value' => $dailyPaymentCount, 'detail' => 'S/ '.number_format($dailyPaymentAmount, 2).' recaudados hoy', 'resource' => 'payments', 'accent' => 'emerald'],
        ];

        foreach ($metrics as &$metric) {
            if (array_key_exists('value', $metric)) {
                continue;
            }

            $query = $metric['model']::query();

            if (($metric['pending'] ?? false) === true) {
                $query->where('status', 'PENDING');
            }

            $metric['value'] = $query->count();
        }
        unset($metric);

        $cashBalance = (float) Payment::query()->sum('amount')
            + (float) Income::query()->sum('amount')
            - (float) Expense::query()->sum('amount');
        $financialMetrics = [
            ['label' => 'Recaudación del mes', 'value' => $monthlyCollection, 'format' => 'currency', 'accent' => 'emerald', 'resource' => 'payments'],
            ['label' => 'Gastos del mes', 'value' => $monthlyExpenses, 'format' => 'currency', 'accent' => 'rose', 'resource' => 'expenses'],
            ['label' => 'Saldo en caja', 'value' => $cashBalance, 'detail' => 'Cobros e ingresos menos gastos', 'format' => 'currency', 'accent' => 'sky', 'resource' => 'cash-closings'],
        ];

        $movements = Payment::query()
            ->with(['customer', 'invoice.connection.property.customer'])
            ->latest('paid_at')
            ->limit(8)
            ->get()
            ->map(function (Payment $payment): array {
                $customer = $payment->customer ?? $payment->invoice?->connection?->property?->customer;

                return [
                    'occurred_at' => $payment->paid_at,
                    'type' => 'Cobro',
                    'code' => $payment->receipt_code,
                    'concept' => $customer ? trim($customer->first_name.' '.$customer->last_name) : 'Pago de cuota',
                    'amount' => (float) $payment->amount,
                    'direction' => 'income',
                ];
            })
            ->concat(Income::query()->with('incomeType')->latest('received_on')->limit(8)->get()->map(fn (Income $income): array => [
                'occurred_at' => $income->received_on,
                'type' => 'Ingreso',
                'code' => $income->income_code,
                'concept' => $income->concept ?: ($income->incomeType?->name ?? 'Ingreso registrado'),
                'amount' => (float) $income->amount,
                'direction' => 'income',
            ]))
            ->concat(Expense::query()->with('expenseCategory')->latest('incurred_on')->limit(8)->get()->map(fn (Expense $expense): array => [
                'occurred_at' => $expense->incurred_on,
                'type' => 'Gasto',
                'code' => $expense->expense_code,
                'concept' => $expense->concept ?: ($expense->expenseCategory?->name ?? 'Gasto registrado'),
                'amount' => (float) $expense->amount,
                'direction' => 'expense',
            ]))
            ->sortByDesc('occurred_at')
            ->take(8)
            ->values();

        $recentInvoices = Invoice::query()
            ->with(['connection.property.customer'])
            ->orderByDesc('issued_on')
            ->limit(6)
            ->get();

        $assemblies = Assembly::query()->orderByDesc('held_on')->limit(30)->get();

        return view('dashboard', compact('metrics', 'financialMetrics', 'movements', 'recentInvoices', 'assemblies', 'reports'));
    }

    public function index(Request $request, string $resource): View
    {
        $definition = $this->definition($resource);
        $columns = $this->columns($definition);
        $records = $this->query($definition);
        $paymentFilters = ['q' => '', 'from' => '', 'to' => ''];

        if ($resource === 'payments') {
            $filters = $request->validate([
                'q' => ['nullable', 'string', 'max:150'],
                'from' => ['nullable', 'date'],
                'to' => ['nullable', 'date', 'after_or_equal:from'],
            ]);
            $paymentFilters = [
                'q' => trim((string) ($filters['q'] ?? '')),
                'from' => (string) ($filters['from'] ?? ''),
                'to' => (string) ($filters['to'] ?? ''),
            ];

            if ($paymentFilters['q'] !== '') {
                $terms = preg_split('/\s+/', $paymentFilters['q'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $matchesName = static function ($customerQuery) use ($terms): void {
                    foreach ($terms as $term) {
                        $customerQuery->where(function ($nameQuery) use ($term): void {
                            $nameQuery->where('first_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%");
                        });
                    }
                };

                $records->where(function ($query) use ($matchesName): void {
                    $query->whereHas('customer', $matchesName)
                        ->orWhereHas('invoice.connection.property.customer', $matchesName);
                });
            }

            if ($paymentFilters['from'] !== '') {
                $records->whereDate('paid_at', '>=', $paymentFilters['from']);
            }

            if ($paymentFilters['to'] !== '') {
                $records->whereDate('paid_at', '<=', $paymentFilters['to']);
            }
        }

        $records = $records
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('resources.index', compact('resource', 'definition', 'columns', 'records', 'paymentFilters'));
    }

    public function create(string $resource): View
    {
        $definition = $this->writableDefinition($resource);

        return view('resources.form', [
            'resource' => $resource,
            'definition' => $definition,
            'record' => null,
            'options' => $this->options($definition),
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $definition = $this->writableDefinition($resource);
        $this->normalizeTimeFields($request, $definition);
        $attributes = $request->validate($this->rules($definition));
        $audit = app(AuditService::class);

        if ($resource === 'meters') {
            $this->ensureMeterCanBeAssigned($attributes);
        }

        if ($resource === 'meter-readings') {
            $record = app(MeterReadingService::class)->create($attributes, $request->user()?->getKey());
        } else {
            $record = $this->modelClass($definition)::create($attributes);
        }
        $audit->created($request->user(), $record);

        return redirect()
            ->route('resources.index', ['resource' => $resource])
            ->with('success', $definition['singular'].' creado correctamente.');
    }

    public function show(string $resource, string $record): View
    {
        $definition = $this->definition($resource);
        $model = $this->find($definition, $record);

        return view('resources.show', [
            'resource' => $resource,
            'definition' => $definition,
            'record' => $model,
            'fields' => $definition['fields'],
        ]);
    }

    public function edit(string $resource, string $record): View
    {
        $definition = $this->writableDefinition($resource);

        return view('resources.form', [
            'resource' => $resource,
            'definition' => $definition,
            'record' => $this->find($definition, $record),
            'options' => $this->options($definition),
        ]);
    }

    public function update(Request $request, string $resource, string $record): RedirectResponse
    {
        $definition = $this->writableDefinition($resource);
        $this->normalizeTimeFields($request, $definition);
        $model = $this->find($definition, $record);
        $attributes = $request->validate($this->rules($definition, true));
        $audit = app(AuditService::class);
        $before = $audit->snapshot($model);

        if ($resource === 'connections') {
            $this->ensureConnectionCanUsePaymentMode($model, $attributes);
        }
        if ($resource === 'meters') {
            $this->ensureMeterCanBeAssigned($attributes, $model);
        }

        if ($resource === 'meter-readings') {
            $model = app(MeterReadingService::class)->update($model, $attributes);
        } else {
            $model->fill($attributes)->save();

            if ($resource === 'meters') {
                app(MeterReadingService::class)->recalculateForMeter($model);
            }
        }
        $audit->updated($request->user(), $model, $before);

        return redirect()
            ->route('resources.show', ['resource' => $resource, 'record' => $model->getKey()])
            ->with('success', $definition['singular'].' actualizado correctamente.');
    }

    public function destroy(string $resource, string $record): RedirectResponse
    {
        $definition = $this->writableDefinition($resource);
        $model = $this->find($definition, $record);
        $audit = app(AuditService::class);
        $before = $audit->snapshot($model);

        try {
            if ($resource === 'meter-readings') {
                app(MeterReadingService::class)->delete($model);
            } else {
                $model->delete();
            }
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar porque el registro tiene información relacionada.');
        }
        $audit->deleted($request->user(), $model, $before);

        return redirect()
            ->route('resources.index', ['resource' => $resource])
            ->with('success', $definition['singular'].' eliminado correctamente.');
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

    /** @param array<string, mixed> $definition */
    private function normalizeTimeFields(Request $request, array $definition): void
    {
        $normalized = [];

        foreach ($definition['fields'] as $name => $field) {
            if (($field['type'] ?? null) !== 'time' || ! $request->filled($name)) {
                continue;
            }

            $value = trim((string) $request->input($name));
            if (preg_match('/^(\d{2}:\d{2})(?::\d{2})?$/', $value, $matches) === 1) {
                $normalized[$name] = $matches[1];
            }
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }
    }

    /** @return array<string, array{title: string, items: list<array<string, string>}>} */
    public static function navigation(): array
    {
        $groups = [
            'Clientes' => ['customers', 'properties'],
            'Servicios' => ['connections', 'connection-usage-types', 'meters', 'meter-readings'],
            'Facturación' => ['invoices', 'payments', 'rates', 'late-fee-settings'],
            'Caja' => [
                'incomes',
                'expenses',
                ['label' => 'Pagos', 'route_name' => 'collections.create', 'active' => 'collections.*', 'permission' => 'payments.create'],
                'cash-closings',
            ],
            'Asambleas' => [
                'assemblies',
                'assembly-attendances',
                'fines',
                ['label' => 'Lector de asistencia', 'route_name' => 'attendance.scanner', 'active' => 'attendance.*', 'permission' => 'reports.view'],
            ],
            'Reportes' => [
                ['label' => 'Operativos', 'route_name' => 'dashboard', 'fragment' => 'reportes-operativos', 'permission' => 'reports.view'],
                ['label' => 'Financieros y comunitarios', 'route_name' => 'dashboard', 'fragment' => 'reportes-financieros-comunitarios', 'permission' => 'reports.view'],
            ],
            'Administración' => ['settings', 'roles', 'permissions', 'audit-logs'],
            'Catálogos' => ['customer-statuses', 'neighborhoods', 'connection-types', 'connection-statuses', 'usage-types', 'payment-methods', 'assembly-types', 'income-types', 'expense-categories'],
        ];
        $definitions = self::definitions();
        $navigation = [];

        foreach ($groups as $title => $items) {
            $navigation[$title] = [
                'title' => $title,
                'items' => array_map(static function (string|array $item) use ($definitions): array {
                    if (is_array($item)) {
                        return $item;
                    }

                    return array_filter([
                        'resource' => $item,
                        'label' => $definitions[$item]['label'],
                        'permission' => self::navigationPermission($item),
                    ], static fn (mixed $value): bool => $value !== null);
                }, $items),
            ];
        }

        return $navigation;
    }

    private static function navigationPermission(string $resource): ?string
    {
        return match ($resource) {
            'customers', 'properties' => 'customers.view',
            'connections', 'connection-usage-types', 'meters', 'meter-readings' => 'services.manage',
            'invoices', 'payments' => 'reports.view',
            'rates', 'billing-periods', 'late-fee-settings' => 'rates.manage',
            'incomes', 'expenses', 'cash-closings' => 'cash.manage',
            'assemblies', 'assembly-attendances', 'fines' => 'assemblies.manage',
            'audit-logs' => 'audit.view',
            'roles', 'permissions', 'settings', 'customer-statuses', 'neighborhoods', 'connection-types', 'connection-statuses', 'usage-types', 'payment-methods', 'assembly-types', 'income-types', 'expense-categories' => 'users.manage',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function writableDefinition(string $resource): array
    {
        $definition = $this->definition($resource);

        abort_if($definition['read_only'] ?? false, 403);

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $resource): array
    {
        $definitions = self::definitions();

        abort_unless(array_key_exists($resource, $definitions), 404);

        return $definitions[$resource];
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function query(array $definition)
    {
        return $this->modelClass($definition)::query()->with($this->relations($definition));
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function find(array $definition, string $id): Model
    {
        return $this->query($definition)->findOrFail($id);
    }

    /**
     * @param array<string, mixed> $definition
     * @return class-string<Model>
     */
    private function modelClass(array $definition): string
    {
        return $definition['model'];
    }

    /**
     * @param array<string, mixed> $definition
     * @return list<string>
     */
    private function relations(array $definition): array
    {
        return collect($definition['fields'])
            ->pluck('relation')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, array<string, string>>
     */
    private function options(array $definition): array
    {
        $options = [];

        foreach ($definition['fields'] as $name => $field) {
            if ($field['type'] !== 'select') {
                continue;
            }

            if (isset($field['choices'])) {
                $options[$name] = $field['choices'];
                continue;
            }

            $option = $field['options'];
            $options[$name] = $this->optionLabels($option['model'], $option['label']);
        }

        return $options;
    }

    /**
     * Relation choices carry enough context to distinguish records in a large
     * list. Generic labels are retained for simple catalog tables.
     *
     * @param class-string<Model> $model
     * @return array<int, string>
     */
    private function optionLabels(string $model, string $fallbackLabel): array
    {
        if ($model === Customer::class) {
            return Customer::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->mapWithKeys(static fn (Customer $customer): array => [$customer->id => trim("{$customer->customer_code} · {$customer->national_id} · {$customer->last_name}, {$customer->first_name}")])
                ->all();
        }

        if ($model === Property::class) {
            return Property::query()
                ->with('customer')
                ->orderBy('property_code')
                ->get()
                ->mapWithKeys(static fn (Property $property): array => [$property->id => trim("{$property->property_code} · {$property->address} · {$property->customer?->last_name}, {$property->customer?->first_name}")])
                ->all();
        }

        if ($model === Connection::class) {
            return Connection::query()
                ->with('property.customer')
                ->orderBy('supply_code')
                ->get()
                ->mapWithKeys(static fn (Connection $connection): array => [$connection->id => trim("{$connection->supply_code} · {$connection->property?->address} · {$connection->property?->customer?->last_name}, {$connection->property?->customer?->first_name}")])
                ->all();
        }

        if ($model === Meter::class) {
            return Meter::query()
                ->with('connection.property.customer')
                ->where('active', true)
                ->whereHas('connection', fn ($query) => $query->where('payment_mode', Connection::PAYMENT_METERED))
                ->orderBy('meter_number')
                ->get()
                ->mapWithKeys(static fn (Meter $meter): array => [$meter->id => trim("{$meter->meter_number} · {$meter->connection?->supply_code} · {$meter->connection?->property?->customer?->last_name}, {$meter->connection?->property?->customer?->first_name}")])
                ->all();
        }

        if ($model === Assembly::class) {
            return Assembly::query()
                ->orderByDesc('held_on')
                ->get()
                ->mapWithKeys(static fn (Assembly $assembly): array => [$assembly->id => "{$assembly->assembly_code} · {$assembly->held_on?->format('d/m/Y')} · {$assembly->place}"])
                ->all();
        }

        return $model::query()
            ->orderBy($fallbackLabel)
            ->pluck($fallbackLabel, 'id')
            ->map(static fn (mixed $label): string => (string) $label)
            ->all();
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, string|list<string>>
     */
    private function rules(array $definition, bool $updating = false): array
    {
        $rules = [];

        foreach ($definition['fields'] as $name => $field) {
            if ($field['readonly'] ?? false) {
                continue;
            }

            $fieldRules = [($field['required'] && !$updating) ? 'required' : 'nullable'];

            switch ($field['type']) {
                case 'select':
                    if (isset($field['choices'])) {
                        $fieldRules[] = 'in:'.implode(',', array_keys($field['choices']));
                    } else {
                        $optionModel = new ($field['options']['model']);
                        $fieldRules[] = 'integer';
                        $fieldRules[] = 'exists:'.$optionModel->getTable().',id';
                    }
                    break;
                case 'checkbox':
                    $fieldRules[] = 'boolean';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    if (isset($field['min'])) {
                        $fieldRules[] = 'min:'.$field['min'];
                    }
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'time':
                    $fieldRules[] = 'date_format:H:i';
                    break;
                case 'datetime-local':
                    $fieldRules[] = 'date';
                    break;
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'password':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'min:8';
                    break;
                default:
                    $fieldRules[] = 'string';
            }

            if (isset($field['max'])) {
                $fieldRules[] = 'max:'.$field['max'];
            }

            $rules[$name] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, array<string, mixed>>
     */
    private function columns(array $definition): array
    {
        $columns = $definition['columns'] ?? array_keys($definition['fields']);

        return collect($columns)
            ->mapWithKeys(static fn (string $name): array => [$name => $definition['fields'][$name]])
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function definitions(): array
    {
        return [
            'customers' => self::resource('Clientes', 'Cliente', Customer::class, [
                'customer_code' => self::code('Código de cliente'),
                'national_id' => self::text('DNI', false, 20),
                'first_name' => self::text('Nombres', true, 100),
                'last_name' => self::text('Apellidos', true, 100),
                'birth_date' => self::date('Fecha de nacimiento', false),
                'phone' => self::text('Teléfono', false, 30),
                'email' => self::email('Correo electrónico', false, 150),
                'address' => self::text('Dirección', false, 250),
                'customer_status_id' => self::select('Estado', CustomerStatus::class, 'customerStatus'),
                'registered_on' => self::date('Fecha de registro'),
                'notes' => self::textarea('Observaciones', false),
            ], ['customer_code', 'national_id', 'first_name', 'last_name', 'customer_status_id']),
            'properties' => self::resource('Predios', 'Predio', Property::class, [
                'customer_id' => self::select('Cliente', Customer::class, 'customer', true, 'last_name'),
                'neighborhood_id' => self::select('Sector', Neighborhood::class, 'neighborhood'),
                'address' => self::text('Dirección', true, 250),
                'reference' => self::text('Referencia', false, 250),
                'property_code' => self::code('Código de predio'),
                'active' => self::checkbox('Activo'),
            ], ['property_code', 'customer_id', 'neighborhood_id', 'address', 'active']),
            'connections' => self::resource('Conexiones', 'Conexión', Connection::class, [
                'property_id' => self::select('Predio', Property::class, 'property', true, 'address'),
                'supply_code' => self::code('Código de suministro'),
                'payment_mode' => self::choice('Modalidad de cobro', [Connection::PAYMENT_FIXED => 'Pago fijo', Connection::PAYMENT_METERED => 'Con medidor'], true, Connection::PAYMENT_FIXED),
                'connection_type_id' => self::select('Tipo de conexión', ConnectionType::class, 'connectionType'),
                'connection_status_id' => self::select('Estado', ConnectionStatus::class, 'connectionStatus'),
                'installed_on' => self::date('Fecha de instalación', false),
                'notes' => self::textarea('Observaciones', false),
            ], ['supply_code', 'property_id', 'payment_mode', 'connection_type_id', 'connection_status_id', 'installed_on']),
            'connection-usage-types' => self::resource('Asignaciones de uso', 'Asignación de uso', ConnectionUsageType::class, [
                'connection_id' => self::select('Conexión', Connection::class, 'connection', true, 'supply_code'),
                'usage_type_id' => self::select('Tipo de uso', UsageType::class, 'usageType'),
                'starts_on' => self::date('Inicio'),
                'ends_on' => self::date('Fin', false),
            ], ['connection_id', 'usage_type_id', 'starts_on', 'ends_on']),
            'meters' => self::resource('Medidores', 'Medidor', Meter::class, [
                'connection_id' => self::select('Conexión', Connection::class, 'connection', true, 'supply_code'),
                'meter_number' => self::text('Número de medidor', true, 50),
                'initial_reading' => self::number('Lectura inicial', true, 0, 0),
                'installed_on' => self::date('Fecha de instalación', false),
                'active' => self::checkbox('Activo', true),
            ], ['meter_number', 'connection_id', 'initial_reading', 'installed_on', 'active']),
            'meter-readings' => self::resource('Lecturas', 'Lectura', MeterReading::class, [
                'meter_id' => self::select('Medidor', Meter::class, 'meter', true, 'meter_number'),
                'read_on' => self::date('Fecha de lectura'),
                'previous_reading' => self::readonlyNumber('Lectura anterior'),
                'current_reading' => self::number('Lectura actual', true, 0),
                'consumption' => self::readonlyNumber('Consumo'),
                'user_id' => self::select('Registrado por', User::class, 'user', false, 'name'),
                'notes' => self::textarea('Observaciones', false),
            ], ['meter_id', 'read_on', 'previous_reading', 'current_reading', 'consumption', 'user_id']),
            'rates' => self::resource('Tarifas', 'Tarifa', Rate::class, [
                'usage_type_id' => self::select('Tipo de uso', UsageType::class, 'usageType'),
                'year' => self::number('Año', true, 2000),
                'amount' => self::number('Importe fijo mensual', true, 0),
                'metered_unit_price' => self::number('Precio por m³ (medidor)', false, 0),
                'starts_on' => self::date('Vigente desde'),
                'ends_on' => self::date('Vigente hasta', false),
                'approved_by_assembly' => self::checkbox('Aprobada por asamblea'),
                'notes' => self::textarea('Notas', false),
            ], ['usage_type_id', 'year', 'amount', 'metered_unit_price', 'starts_on', 'approved_by_assembly']),
            'billing-periods' => self::resource('Ciclos de pago', 'Ciclo de pago', BillingPeriod::class, [
                'months' => self::number('Meses', true, 1),
                'description' => self::text('Descripción', false, 100),
            ], ['months', 'description']),
            'late-fee-settings' => self::resource('Configuración de mora', 'Configuración de mora', LateFeeSetting::class, [
                'monthly_amount' => self::number('Monto mensual', true, 0),
                'grace_months' => self::number('Meses de gracia', true, 1),
                'starts_on' => self::date('Inicio de vigencia'),
                'ends_on' => self::date('Fin de vigencia', false),
            ], ['monthly_amount', 'grace_months', 'starts_on', 'ends_on']),
            'invoices' => self::resource('Cuotas mensuales', 'Cuota mensual', Invoice::class, [
                'invoice_code' => self::code('Código de factura'),
                'connection_id' => self::select('Conexión', Connection::class, 'connection', true, 'supply_code'),
                'billing_period_id' => self::select('Periodo de cobro', BillingPeriod::class, 'billingPeriod', true, 'description'),
                'issued_on' => self::date('Fecha de emisión'),
                'due_on' => self::date('Fecha de vencimiento'),
                'period_starts_on' => self::date('Periodo desde'),
                'period_ends_on' => self::date('Periodo hasta'),
                'rate' => self::number('Tarifa', true, 0),
                'late_fee' => self::number('Mora', true, 0),
                'fines' => self::number('Multas', true, 0),
                'total' => self::number('Total', true, 0),
                'status' => self::choice('Estado', ['PENDING' => 'Pendiente', 'PAID' => 'Pagada', 'CANCELLED' => 'Anulada']),
            ], ['invoice_code', 'connection_id', 'period_starts_on', 'due_on', 'total', 'status'], true),
            'payments' => self::resource('Recibos de pago', 'Recibo de pago', Payment::class, [
                'receipt_code' => self::code('Número de recibo'),
                'customer_id' => self::select('Cliente', Customer::class, 'customer', false, 'last_name'),
                'invoice_id' => self::select('Factura histórica', Invoice::class, 'invoice', false, 'invoice_code'),
                'paid_at' => self::datetime('Fecha y hora de pago'),
                'amount' => self::number('Monto', true, 0.01),
                'payment_method_id' => self::select('Método de pago', PaymentMethod::class, 'paymentMethod'),
                'source' => self::choice('Origen', ['COUNTER' => 'Ventanilla', 'WEB' => 'Web']),
                'user_id' => self::select('Registrado por', User::class, 'user', false, 'name'),
                'operation_number' => self::text('Número de operación', false, 100),
                'notes' => self::textarea('Observaciones', false),
            ], ['receipt_code', 'customer_id', 'paid_at', 'amount', 'payment_method_id', 'source'], true),
            'assemblies' => self::resource('Asambleas', 'Asamblea', Assembly::class, [
                'assembly_code' => self::code('Código de asamblea'),
                'assembly_type_id' => self::select('Tipo de asamblea', AssemblyType::class, 'assemblyType'),
                'held_on' => self::date('Fecha'),
                'held_at' => self::time('Hora', false),
                'place' => self::text('Lugar', false, 250),
                'description' => self::textarea('Descripción', false),
                'absence_fine' => self::number('Multa por inasistencia', true, 0),
                'status' => self::choice('Estado', ['SCHEDULED' => 'Programada', 'HELD' => 'Realizada', 'CANCELLED' => 'Cancelada']),
            ], ['assembly_code', 'assembly_type_id', 'held_on', 'held_at', 'place', 'status']),
            'assembly-attendances' => self::resource('Asistencias', 'Asistencia', AssemblyAttendance::class, [
                'assembly_id' => self::select('Asamblea', Assembly::class, 'assembly', true, 'held_on'),
                'customer_id' => self::select('Cliente', Customer::class, 'customer', true, 'last_name'),
                'attended' => self::checkbox('Asistió'),
                'notes' => self::textarea('Observaciones', false),
            ], ['assembly_id', 'customer_id', 'attended', 'notes']),
            'fines' => self::resource('Multas', 'Multa', Fine::class, [
                'fine_code' => self::code('Código de multa'),
                'customer_id' => self::select('Cliente', Customer::class, 'customer', true, 'last_name'),
                'assembly_id' => self::select('Asamblea', Assembly::class, 'assembly', true, 'held_on'),
                'reason' => self::text('Motivo', false, 250),
                'amount' => self::number('Monto', true, 0.01),
                'generated_on' => self::date('Fecha de generación'),
                'status' => self::choice('Estado', ['PENDING' => 'Pendiente', 'PAID' => 'Pagada', 'CANCELLED' => 'Anulada']),
            ], ['fine_code', 'customer_id', 'assembly_id', 'amount', 'generated_on', 'status'], true),
            'incomes' => self::resource('Ingresos', 'Ingreso', Income::class, [
                'income_code' => self::code('Código de ingreso'),
                'income_type_id' => self::select('Tipo de ingreso', IncomeType::class, 'incomeType'),
                'received_on' => self::date('Fecha de recepción'),
                'concept' => self::text('Concepto', false, 250),
                'amount' => self::number('Monto', true, 0.01),
                'user_id' => self::select('Registrado por', User::class, 'user', false, 'name'),
                'reference' => self::text('Referencia', false, 100),
            ], ['income_code', 'income_type_id', 'received_on', 'concept', 'amount']),
            'expenses' => self::resource('Egresos', 'Egreso', Expense::class, [
                'expense_code' => self::code('Código de egreso'),
                'expense_category_id' => self::select('Categoría', ExpenseCategory::class, 'expenseCategory'),
                'incurred_on' => self::date('Fecha'),
                'concept' => self::text('Concepto', false, 250),
                'amount' => self::number('Monto', true, 0.01),
                'user_id' => self::select('Registrado por', User::class, 'user', false, 'name'),
                'receipt' => self::text('Comprobante', false, 100),
            ], ['expense_code', 'expense_category_id', 'incurred_on', 'concept', 'amount']),
            'cash-closings' => self::resource('Cierres de caja', 'Cierre de caja', CashClosing::class, [
                'year' => self::number('Año', true, 2000),
                'month' => self::number('Mes', true, 1),
                'total_income' => self::number('Total de ingresos', true, 0),
                'total_expense' => self::number('Total de egresos', true, 0),
                'balance' => self::number('Saldo', true),
                'user_id' => self::select('Cerrado por', User::class, 'user', false, 'name'),
                'closed_at' => self::datetime('Fecha de cierre', false),
            ], ['year', 'month', 'total_income', 'total_expense', 'balance', 'closed_at']),
            'customer-statuses' => self::catalog('Estados de cliente', 'Estado de cliente', CustomerStatus::class),
            'neighborhoods' => self::resource('Sectores', 'Sector', Neighborhood::class, [
                'name' => self::text('Nombre', true, 100),
                'description' => self::text('Descripción', false, 255),
                'active' => self::checkbox('Activo'),
            ], ['name', 'description', 'active']),
            'connection-types' => self::catalog('Tipos de conexión', 'Tipo de conexión', ConnectionType::class, 200),
            'connection-statuses' => self::catalog('Estados de conexión', 'Estado de conexión', ConnectionStatus::class, 200),
            'usage-types' => self::catalog('Tipos de uso', 'Tipo de uso', UsageType::class, 200),
            'payment-methods' => self::singleName('Métodos de pago', 'Método de pago', PaymentMethod::class, 50),
            'assembly-types' => self::catalog('Tipos de asamblea', 'Tipo de asamblea', AssemblyType::class, 200),
            'income-types' => self::singleName('Tipos de ingreso', 'Tipo de ingreso', IncomeType::class),
            'expense-categories' => self::singleName('Categorías de egreso', 'Categoría de egreso', ExpenseCategory::class),
            'roles' => self::catalog('Roles', 'Rol', Role::class),
            'permissions' => self::catalog('Permisos', 'Permiso', Permission::class),
            'settings' => self::resource('Configuraciones', 'Configuración', Setting::class, [
                'key' => self::text('Clave', true, 100),
                'value' => self::text('Valor', true, 255),
                'description' => self::text('Descripción', false, 255),
            ], ['key', 'value', 'description']),
            'audit-logs' => self::resource('Bitácora de auditoría', 'Evento de auditoría', AuditLog::class, [
                'user_id' => self::select('Usuario', User::class, 'user', false, 'name'),
                'table_name' => self::text('Tabla', true, 100),
                'record_id' => self::number('ID del registro', true, 1),
                'action' => self::choice('Acción', ['INSERT' => 'Creación', 'UPDATE' => 'Actualización', 'DELETE' => 'Eliminación']),
                'old_values' => self::textarea('Valores anteriores', false),
                'new_values' => self::textarea('Valores nuevos', false),
                'occurred_at' => self::datetime('Fecha y hora'),
            ], ['user_id', 'table_name', 'record_id', 'action', 'occurred_at'], true),
        ];
    }

    /** @return array<string, mixed> */
    private static function resource(string $label, string $singular, string $model, array $fields, array $columns = [], bool $readOnly = false): array
    {
        return [
            'label' => $label,
            'singular' => $singular,
            'model' => $model,
            'fields' => $fields,
            'columns' => $columns,
            'read_only' => $readOnly,
        ];
    }

    /** @return array<string, mixed> */
    private static function catalog(string $label, string $singular, string $model, int $descriptionMax = 255): array
    {
        return self::resource($label, $singular, $model, [
            'name' => self::text('Nombre', true, 100),
            'description' => self::text('Descripción', false, $descriptionMax),
        ], ['name', 'description']);
    }

    /** @return array<string, mixed> */
    private static function singleName(string $label, string $singular, string $model, int $max = 100): array
    {
        return self::resource($label, $singular, $model, [
            'name' => self::text('Nombre', true, $max),
        ], ['name']);
    }

    /** @return array<string, mixed> */
    private static function text(string $label, bool $required = true, ?int $max = null): array
    {
        return self::field($label, 'text', $required, $max === null ? [] : ['max' => $max]);
    }

    /** @return array<string, mixed> */
    private static function code(string $label): array
    {
        return self::field($label, 'text', false, ['readonly' => true]);
    }

    /** @return array<string, mixed> */
    private static function email(string $label, bool $required = true, ?int $max = null): array
    {
        return self::field($label, 'email', $required, $max === null ? [] : ['max' => $max]);
    }

    /** @return array<string, mixed> */
    private static function textarea(string $label, bool $required = true): array
    {
        return self::field($label, 'textarea', $required);
    }

    /** @return array<string, mixed> */
    private static function date(string $label, bool $required = true): array
    {
        return self::field($label, 'date', $required);
    }

    /** @return array<string, mixed> */
    private static function time(string $label, bool $required = true): array
    {
        return self::field($label, 'time', $required);
    }

    /** @return array<string, mixed> */
    private static function datetime(string $label, bool $required = true): array
    {
        return self::field($label, 'datetime-local', $required);
    }

    /** @return array<string, mixed> */
    private static function number(string $label, bool $required = true, int|float|null $min = null, int|float|null $default = null): array
    {
        return self::field($label, 'number', $required, array_filter([
            'min' => $min,
            'default' => $default,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /** @return array<string, mixed> */
    private static function checkbox(string $label, bool $default = false): array
    {
        return self::field($label, 'checkbox', false, ['default' => $default]);
    }

    /** @return array<string, mixed> */
    private static function select(string $label, string $model, string $relation, bool $required = true, string $optionLabel = 'name'): array
    {
        return self::field($label, 'select', $required, [
            'relation' => $relation,
            'options' => ['model' => $model, 'label' => $optionLabel],
        ]);
    }

    /** @param array<string, string> $choices
     * @return array<string, mixed>
     */
    private static function choice(string $label, array $choices, bool $required = true, string|int|null $default = null): array
    {
        return self::field($label, 'select', $required, array_filter([
            'choices' => $choices,
            'default' => $default,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /** @return array<string, mixed> */
    private static function readonlyNumber(string $label): array
    {
        return self::field($label, 'number', false, ['readonly' => true]);
    }

    /** @return array<string, mixed> */
    private static function field(string $label, string $type, bool $required, array $extra = []): array
    {
        return array_merge(compact('label', 'type', 'required'), $extra);
    }
}
