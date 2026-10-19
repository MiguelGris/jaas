<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ServiceWaiver;
use App\Support\ResourceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ReceiptIndexController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(ResourceAccess::allows($request->user(), 'payments', 'index'), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'kind' => ['nullable', 'in:all,payments,condonations'], 'per_page' => ['nullable', 'integer', 'in:25,50,100,200'], 'page' => ['nullable', 'integer', 'min:1']]);
        $q = trim((string) ($filters['q'] ?? ''));
        $customerMatch = static function ($query) use ($q): void {
            foreach (preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
                $query->where(fn ($name) => $name->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('business_name', 'like', "%{$term}%")->orWhere('national_id', 'like', "%{$term}%")->orWhere('customer_code', 'like', "%{$term}%"));
            }
        };
        $payments = Payment::query();
        $waivers = ServiceWaiver::query();
        if ($q !== '') {
            $payments->where(fn ($query) => $query->where('receipt_code', 'like', "%{$q}%")->orWhere('operation_number', 'like', "%{$q}%")->orWhereHas('customer', $customerMatch)->orWhereHas('invoice.connection.property.customer', $customerMatch));
            $waivers->where(fn ($query) => $query->where('receipt_code', 'like', "%{$q}%")->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_code', 'like', "%{$q}%"))->orWhereHas('invoice.connection.property.customer', $customerMatch));
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if (! empty($filters[$key])) {
                $payments->whereDate('paid_at', $operator, $filters[$key]);
                $waivers->whereDate('created_at', $operator, $filters[$key]);
            }
        }
        if (($filters['kind'] ?? 'all') === 'payments') {
            $waivers->whereRaw('1=0');
        }
        if (($filters['kind'] ?? 'all') === 'condonations') {
            $payments->whereRaw('1=0');
        }
        $union = $payments->select('id', DB::raw('paid_at AS occurred_at'), DB::raw("'payment' AS kind"))->toBase()
            ->unionAll($waivers->select('id', DB::raw('created_at AS occurred_at'), DB::raw("'service_waiver' AS kind"))->toBase());
        $records = DB::query()->fromSub($union, 'receipt_rows')->orderByDesc('occurred_at')->orderBy('kind')->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
        $payments = Payment::query()->whereIn('id', $records->getCollection()->where('kind', 'payment')->pluck('id'))->with(['customer', 'invoice.connection.property.customer', 'paymentMethod'])->get()->keyBy('id');
        $waivers = ServiceWaiver::query()->whereIn('id', $records->getCollection()->where('kind', 'service_waiver')->pluck('id'))->with(['invoice.connection.property.customer'])->get()->keyBy('id');
        $records->setCollection($records->getCollection()->map(function ($row) use ($payments, $waivers) {
            $isWaiver = $row->kind === 'service_waiver';
            $record = $isWaiver ? $waivers[$row->id] : $payments[$row->id];
            $customer = $isWaiver ? $record->invoice->connection->property->customer : ($record->customer ?? $record->invoice?->connection?->property?->customer);

            return (object) ['id' => $record->id, 'kind' => $row->kind, 'receipt_code' => $record->receipt_code, 'date' => $isWaiver ? $record->created_at : $record->paid_at,
                'customer' => $customer, 'customer_name' => $customer?->display_name ?? 'Cliente no asociado', 'operation_number' => $isWaiver ? '—' : $record->operation_number,
                'amount' => $isWaiver ? 0.0 : (float) $record->amount, 'waived_amount' => $isWaiver ? (float) $record->amount : 0.0,
                'status' => $isWaiver ? ((float) $record->balance_after <= 0 ? 'Condonado' : 'Condonado parcialmente') : ($record->status === Payment::STATUS_VOIDED ? 'Anulado' : 'Válido'),
                'period' => $isWaiver ? $record->invoice->period_starts_on->format('m/Y') : '—',
                'print_url' => $isWaiver ? route('service-waiver-receipts.show', $record) : route('receipts.thermal', $record),
                'detail_url' => $isWaiver ? route('service-waiver-receipts.show', $record) : route('resources.show', ['resource' => 'payments', 'record' => $record->id]),
                'can_annul' => ! $isWaiver && $record->status === Payment::STATUS_ACTIVE,
            ];
        }));

        return view('receipts.index',compact('records','filters'));
    }
}
