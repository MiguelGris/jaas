<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Neighborhood;
use App\Models\ServiceWaiver;
use App\Models\WaiverCampaign;
use App\Services\BulkWaiverService;
use App\Support\ResourceAccess;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BulkWaiverController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless(ResourceAccess::isAdministrator($request->user()), 403);
    }

    private function filters(Request $request): array
    {
        return $request->validate(['q' => ['nullable', 'string', 'max:150'], 'kind' => ['nullable', 'in:all,service,both,mora,fines'],
            'neighborhood_id' => ['nullable', 'integer', 'exists:neighborhoods,id'], 'until' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100,200'], 'page' => ['nullable', 'integer', 'min:1']]);
    }

    private function paginate($rows, Request $request, int $size = 25): LengthAwarePaginator
    {
        $page = max(1, (int) $request->input('page', 1));

        return (new LengthAwarePaginator(collect($rows)->forPage($page, $size)->values(), count($rows), $size, $page, ['path' => $request->url()]))->withQueryString();
    }

    public function index(Request $request, BulkWaiverService $service)
    {
        $this->authorizeAdmin($request);
        $filters = array_replace(['kind' => 'all'], $this->filters($request));
        $eligible = $service->eligible($filters);

        return view('waivers.index', ['filters' => $filters, 'rows' => $this->paginate($eligible, $request, (int) ($filters['per_page'] ?? 25)),
            'eligibleTotal' => $eligible->sum('balance'), 'neighborhoods' => Neighborhood::query()->orderBy('name')->get(),
            'campaigns' => WaiverCampaign::query()->with('user')->latest()->paginate((int) ($filters['per_page'] ?? 25), ['*'], 'history_page')->withQueryString()]);
    }

    public function preview(Request $request, BulkWaiverService $service)
    {
        $this->authorizeAdmin($request);
        $filters = $this->filters($request);
        $data = $request->validate(['scope' => ['required', 'in:selected,filtered'], 'debts' => ['nullable', 'array'], 'debts.*' => ['string', 'distinct', 'regex:/^(service|mora|fine):[1-9][0-9]*$/'],
            'mode' => ['required', 'in:all,percentage,amount'], 'value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'], 'reason' => ['required', 'string', 'max:250']]);
        $eligible = $service->eligible($filters);
        if ($data['scope'] === 'selected') {
            $selected = $data['debts'] ?? [];
            $eligible = $eligible->whereIn('key', $selected)->values();
            if ($eligible->count() !== count($selected)) {
                throw ValidationException::withMessages(['campaign' => 'Una deuda seleccionada no está disponible. Actualiza la lista.']);
            }
        }
        $rows = $service->plan($eligible, $data['mode'], (float) ($data['value'] ?? 0), perCustomer: true);
        $token = (string) Str::uuid();
        $snapshot = ['token' => $token, 'user_id' => $request->user()->id, 'rows' => $rows, 'mode' => $data['mode'] === 'amount' ? 'amount_per_customer' : $data['mode'], 'value' => (float) ($data['value'] ?? 0), 'reason' => trim($data['reason']), 'created_at' => now()->toIso8601String()];
        $request->session()->put('bulk_waiver_preview', $snapshot);

        return redirect()->route('waivers.review', ['token' => $token]);
    }

    private function snapshot(Request $request): array
    {
        $snapshot = $request->session()->get('bulk_waiver_preview');
        if (! $snapshot || ($snapshot['token'] ?? '') !== $request->input('token') || ($snapshot['user_id'] ?? null) !== $request->user()->id) {
            throw ValidationException::withMessages(['campaign' => 'La revisión no está disponible. Prepara de nuevo la campaña.']);
        }

        return $snapshot;
    }

    public function review(Request $request)
    {
        $this->authorizeAdmin($request);
        $snapshot = $this->snapshot($request);

        return view('waivers.review', ['snapshot' => $snapshot, 'rows' => $this->paginate($snapshot['rows'], $request, (int) ($this->filters($request)['per_page'] ?? 25))]);
    }

    public function store(Request $request, BulkWaiverService $service)
    {
        $this->authorizeAdmin($request);
        $request->validate(['token' => ['required', 'uuid'], 'confirmed' => ['accepted']]);
        $snapshot = $this->snapshot($request);
        $campaign = $service->apply($snapshot, $request->user());
        $request->session()->forget('bulk_waiver_preview');

        return redirect()->route('waivers.index')->with('success', 'Campaña #'.$campaign->id.' aplicada: S/ '.number_format((float) $campaign->total_late_fees, 2).' de moras, S/ '.number_format((float) $campaign->total_fines, 2).' de multas y S/ '.number_format((float) $campaign->total_services, 2).' de servicio. Registrada en la bitácora.');
    }

    public function receipts(Request $request, WaiverCampaign $campaign)
    {
        $this->authorizeAdmin($request);

        return view('waivers.service-receipts', ['campaign' => $campaign, 'waivers' => ServiceWaiver::query()->where('campaign_id', $campaign->id)->with(['invoice.connection.property.customer'])->orderBy('id')->paginate((int) ($this->filters($request)['per_page'] ?? 25))->withQueryString()]);
    }
}
