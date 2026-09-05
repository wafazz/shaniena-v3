<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\MemberHq;
use App\Models\RoleAccess;
use App\Services\PageAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

    public const STATUS_BANNED = 2;

    /** HQ/Owner cannot be assigned through this screen, as in the source. */
    private const ASSIGNABLE_ROLES = [
        MemberHq::ROLE_ACCOUNT,
        MemberHq::ROLE_STAFF_ADMIN,
        MemberHq::ROLE_STAFF_SALES,
        MemberHq::ROLE_STAFF_LOGISTIC,
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Staff/Index', [
            'staff' => MemberHq::query()
                ->withTrashed()
                ->orderBy('id')
                ->get()
                ->map(fn (MemberHq $member) => [
                    'id' => $member->id,
                    'reference' => '#'.str_pad((string) $member->id, 6, '0', STR_PAD_LEFT),
                    'name' => $member->full_name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'role' => $member->role,
                    'role_label' => $member->roleLabel(),
                    'status' => (int) $member->status,
                    'deleted' => $member->trashed(),
                    'registered_at' => $member->created_at?->format('j F, Y h:i A'),
                    'updated_at' => $member->updated_at?->format('j F, Y h:i A'),
                ])->all(),
            'roles' => collect(self::ASSIGNABLE_ROLES)
                ->mapWithKeys(fn (int $role) => [$role => MemberHq::ROLES[$role]])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'f_name' => ['required', 'string', 'max:255'],
            'l_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('member_hq', 'email')],
            'phone' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
            // The source validated these in the browser only, so the endpoint
            // accepted anything — and stored it as unsalted SHA-256.
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->symbols()],
        ]);

        $member = MemberHq::create($data + ['sec_pin' => '', 'status' => self::STATUS_ACTIVE]);

        Activity::record(
            (int) $request->user('admin')->getKey(),
            "Registered staff {$member->full_name}",
            "member_hq|{$member->id}",
            'staff_activity',
        );

        return back()->with('success', "{$member->full_name} can now sign in.");
    }

    public function edit(Request $request, MemberHq $staff, PageAccess $access): Response
    {
        $this->guardTarget($request, $staff);

        return Inertia::render('Admin/Staff/Edit', [
            'staff' => [
                'id' => $staff->id,
                'reference' => '#'.str_pad((string) $staff->id, 6, '0', STR_PAD_LEFT),
                'f_name' => $staff->f_name,
                'l_name' => $staff->l_name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'role' => $staff->role,
                'status' => (int) $staff->status,
            ],
            'roles' => collect(self::ASSIGNABLE_ROLES)
                ->mapWithKeys(fn (int $role) => [$role => MemberHq::ROLES[$role]])
                ->all(),
            'permissions' => RoleAccess::query()
                ->sorted()
                ->get()
                ->map(fn (RoleAccess $row) => [
                    'id' => $row->id,
                    'slug' => $row->page_url,
                    'name' => $row->name,
                    'granted' => $row->allows($staff->id),
                ])->all(),
        ]);
    }

    public function update(Request $request, MemberHq $staff): RedirectResponse
    {
        $this->guardTarget($request, $staff);

        $data = $request->validate([
            'f_name' => ['required', 'string', 'max:255'],
            'l_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('member_hq', 'email')->ignore($staff->id)],
            'phone' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
            'status' => ['required', Rule::in([self::STATUS_INACTIVE, self::STATUS_ACTIVE, self::STATUS_BANNED])],
        ]);

        $staff->update($data);

        return back()->with('success', "{$staff->full_name} updated.");
    }

    /** Grant or revoke one permission. */
    public function setPermission(Request $request, MemberHq $staff, PageAccess $access): RedirectResponse
    {
        $this->guardTarget($request, $staff);

        $data = $request->validate([
            'slug' => ['required', 'string', Rule::exists('role_access', 'page_url')],
            'granted' => ['required', 'boolean'],
        ]);

        $row = RoleAccess::query()->where('page_url', $data['slug'])->firstOrFail();
        $ids = $row->allowedUserIds();

        $ids = $data['granted']
            ? array_unique([...$ids, $staff->id])
            : array_values(array_diff($ids, [$staff->id]));

        $row->setAllowedUserIds($ids);
        $row->save();

        $access->flushFor($staff->id);

        Activity::record(
            (int) $request->user('admin')->getKey(),
            ($data['granted'] ? 'Granted' : 'Revoked')." {$row->name} for {$staff->full_name}",
            "role_access|{$row->id}",
            'permission_activity',
        );

        return back()->with('success', ($data['granted'] ? 'Granted ' : 'Revoked ').$row->name.'.');
    }

    /**
     * Nobody edits their own access, and account 1 (HQ/Owner) is off limits —
     * both enforced in the source's controller and kept.
     */
    private function guardTarget(Request $request, MemberHq $staff): void
    {
        abort_if((int) $staff->getKey() === (int) $request->user('admin')->getKey(), 403, "You can't change your own access.");
        abort_if((int) $staff->getKey() === 1, 403, 'The HQ/Owner account cannot be edited here.');
    }
}
