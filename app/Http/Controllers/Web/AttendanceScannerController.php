<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Assembly;
use App\Models\AssemblyAttendance;
use App\Models\Customer;
use App\Services\AuditService;
use App\Services\OperationalRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AttendanceScannerController extends Controller
{
    public function create(Request $request): View
    {
        $assemblies = Assembly::query()
            ->where('status', 'SCHEDULED')
            ->orderBy('held_on')
            ->get();
        $selectedAssemblyId = $request->integer('assembly');
        $selectedAssembly = $assemblies->firstWhere('id', $selectedAssemblyId);
        $attendees = collect();
        $attendanceCount = 0;
        $attendanceTotal = 0;

        if ($selectedAssembly !== null) {
            $attendanceQuery = AssemblyAttendance::query()
                ->where('assembly_id', $selectedAssembly->getKey());
            $attendanceTotal = (clone $attendanceQuery)->count();
            $attendees = $attendanceQuery
                ->where('attended', true)
                ->with('customer')
                ->orderByRaw('CASE WHEN attended_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('attended_at')
                ->orderBy('id')
                ->get()
                ->values();
            $attendanceCount = $attendees->count();
        }

        return view('attendance.scanner', [
            'assemblies' => $assemblies,
            'selectedAssemblyId' => $selectedAssemblyId,
            'selectedAssembly' => $selectedAssembly,
            'attendees' => $attendees,
            'attendanceCount' => $attendanceCount,
            'attendanceTotal' => $attendanceTotal,
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
        $alreadyPresent = $attendance->exists && $attendance->attended;
        app(OperationalRecordService::class)->attendance([
            'assembly_id' => $assembly->getKey(), 'customer_id' => $customer->getKey(), 'attended' => true,
        ], scheduledOnly: true);

        $name = $customer->display_name;
        $message = $alreadyPresent
            ? "{$name} ya estaba registrado como asistente."
            : "Asistencia registrada: {$name}.";

        return redirect()
            ->route('attendance.scanner', ['assembly' => $assembly->getKey()])
            ->with('attendance_name', $name)
            ->with('attendance_already_present', $alreadyPresent)
            ->with('attendance_message', $message);
    }
}
