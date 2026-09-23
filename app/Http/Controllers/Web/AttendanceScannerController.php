<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Assembly;
use App\Models\AssemblyAttendance;
use App\Models\Customer;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

final class AttendanceScannerController extends Controller
{
    public function __construct()
    {
        view()->share('navigation', JassPageController::navigation());
    }

    public function create(Request $request): View
    {
        $assemblies = Assembly::query()
            ->where('status', 'SCHEDULED')
            ->orderBy('held_on')
            ->get();

        return view('attendance.scanner', [
            'assemblies' => $assemblies,
            'selectedAssemblyId' => $request->integer('assembly'),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'assembly_id' => ['required', 'integer', 'exists:assemblies,id'],
            'barcode' => ['required', 'string', 'max:1000'],
        ]);

        preg_match('/\d{8}/', $data['barcode'], $match);

        if ($match === []) {
            throw ValidationException::withMessages([
                'barcode' => 'No se encontró un DNI válido de 8 dígitos en la lectura.',
            ]);
        }

        $assembly = Assembly::query()
            ->whereKey($data['assembly_id'])
            ->where('status', 'SCHEDULED')
            ->first();

        if ($assembly === null) {
            throw ValidationException::withMessages([
                'assembly_id' => 'La asamblea debe estar programada para registrar asistencias.',
            ]);
        }

        $customer = Customer::query()->where('national_id', $match[0])->first();

        if ($customer === null) {
            throw ValidationException::withMessages([
                'barcode' => 'El DNI leído no corresponde a un cliente registrado.',
            ]);
        }

        $attendance = AssemblyAttendance::query()->firstOrNew([
            'assembly_id' => $assembly->getKey(),
            'customer_id' => $customer->getKey(),
        ]);
        $before = $attendance->exists ? $audit->snapshot($attendance) : null;
        $alreadyPresent = $attendance->exists && $attendance->attended;
        $attendance->attended = true;
        $attendance->save();

        if ($before === null) {
            $audit->created($request->user(), $attendance);
        } else {
            $audit->updated($request->user(), $attendance, $before);
        }

        $name = trim($customer->first_name.' '.$customer->last_name);
        $message = $alreadyPresent
            ? "{$name} ya estaba registrado como asistente."
            : "Asistencia registrada: {$name}.";

        return redirect()
            ->route('attendance.scanner', ['assembly' => $assembly->getKey()])
            ->with('success', $message);
    }
}
