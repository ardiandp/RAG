<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogsController extends Controller
{
    public function index(Request $request): View
    {
        $log = AuditLog::query()
            ->with('user')
            ->latest('id');

        if (! $request->user()->isAdmin()) {
            $log->where('user_id', $request->user()->id);
        }

        if (($action = $request->query('action'))) {
            $log->where('action', 'ilike', "%{$action}%");
        }

        if (($tool = $request->query('tool'))) {
            $log->where('context->tool_name', 'ilike', "%{$tool}%");
        }

        return view('dashboard.audit_logs.index', [
            'logs' => $log->paginate(15)->withQueryString(),
        ]);
    }
}
