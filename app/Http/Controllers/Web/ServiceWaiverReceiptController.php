<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ServiceWaiver;
use App\Support\ResourceAccess;
use Illuminate\Http\Request;

final class ServiceWaiverReceiptController extends Controller
{
    public function show(Request $request, ServiceWaiver $waiver)
    {
        abort_unless(ResourceAccess::allows($request->user(), 'payments', 'store') || ResourceAccess::allows($request->user(), 'payments', 'index'), 403);
        $waiver->load(['invoice.connection.property.customer', 'user']);

        return view('receipts.service-waiver', ['waiver' => $waiver, 'customer' => $waiver->invoice->connection->property->customer]);
    }
}
