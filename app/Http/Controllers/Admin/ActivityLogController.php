<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\MemberHq;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = [
            'user' => $request->integer('user') ?: null,
            'type' => trim((string) $request->query('type', '')) ?: null,
        ];

        return Inertia::render('Admin/Reports/ActivityLog', [
            'filters' => $filters,
            'staff' => MemberHq::query()->orderBy('f_name')->get()->map(fn (MemberHq $m) => [
                'id' => $m->id,
                'name' => $m->full_name,
            ])->all(),
            'types' => Activity::query()->distinct()->orderBy('activities')->pluck('activities'),
            'entries' => Activity::query()
                ->with('user:id,f_name,l_name')
                ->when($filters['user'], fn ($q, $id) => $q->where('user_id', $id))
                ->when($filters['type'], fn ($q, $type) => $q->where('activities', $type))
                ->latest('id')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (Activity $row) => [
                    'id' => $row->id,
                    'description' => $row->description,
                    'table' => $row->table_name,
                    'type' => $row->activities,
                    'actor' => $row->user ? trim("{$row->user->f_name} {$row->user->l_name}") : 'Unknown staff',
                    'at' => $row->created_at?->format('j M Y, h:iA'),
                ]),
        ]);
    }
}
