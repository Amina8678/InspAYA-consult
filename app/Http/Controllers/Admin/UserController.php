<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * CMS users (FR-ADM-11). Routes carry permission: middleware; every action
 * also authorizes against UserPolicy. There is no delete screen or route:
 * UserPolicy::delete() always refuses, so deactivation is the only removal
 * path (D6). Deactivating is caught for every one of that user's active
 * sessions by the existing `active` route middleware on their next request.
 */
class UserController extends Controller
{
    public const PER_PAGE = 20;

    private const AUDITED = ['name', 'email', 'username', 'role_id', 'status'];

    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->query('q'));
        $like = '%'.addcslashes($search, '%_\\').'%';

        $users = User::query()
            ->with('role:id,name')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('username', 'like', $like)))
            ->orderBy('name')
            ->paginate(self::PER_PAGE, ['id', 'name', 'email', 'username', 'role_id', 'status', 'last_login_at'])
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'search' => $search]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', ['targetUser' => new User, 'roles' => $this->roleOptions()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = new User($request->only(['name', 'email', 'username']));
        // Never reachable by a normal sign-in: the account gets its real
        // password only through the reset link sent below.
        $user->password = Hash::make(Str::random(40));
        $user->role_id = (int) $request->validated('role_id');
        $user->save();

        $this->sendPasswordResetLink($user);

        $this->audit->record('created', $request->user(), $user, new: $user->only(self::AUDITED));

        return redirect()->route('admin.users.edit', $user)
            ->with('status', 'Created "'.$user->name.'". A password setup link has been sent to '.$user->email.'.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.form', ['targetUser' => $user, 'roles' => $this->roleOptions()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $before = $user->only(self::AUDITED);
        $actor = $request->user();

        $user->fill($request->only(['name', 'email', 'username']));
        $this->applyRole($request, $user);
        $statusChange = $this->applyStatus($request, $user);

        $changed = array_keys($user->getDirty());

        if ($changed === []) {
            return redirect()->route('admin.users.edit', $user)->with('status', 'No changes to save.');
        }

        $user->save();

        $this->audit->record('updated', $actor, $user,
            array_intersect_key($before, array_flip($changed)),
            $user->only(array_intersect(self::AUDITED, $changed)),
        );
        if ($statusChange !== null) {
            $this->audit->record($statusChange ? 'deactivated' : 'reactivated', $actor, $user,
                ['status' => $before['status']->value], ['status' => $user->status->value]);
        }

        return redirect()->route('admin.users.edit', $user)->with('status', match ($statusChange) {
            true => 'Deactivated "'.$user->name.'".',
            false => 'Reactivated "'.$user->name.'".',
            null => 'Saved "'.$user->name.'".',
        });
    }

    /**
     * FR-ADM-11 "reset passwords for CMS users": the only path store() had
     * was a link sent at account creation, with nothing for an existing,
     * locked-out user. Reuses the exact same sendPasswordResetLink() call as
     * store() — never a second mechanism. UserPolicy::resetPassword() allows
     * a self-reset (no `! $actor->is($target)` check, unlike deactivate/
     * assignRole) but, like every other user-management action, still
     * refuses an Administrator acting on a Super Admin.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $this->sendPasswordResetLink($user);

        $this->audit->record('password_reset_link_sent', $request->user(), $user);

        return redirect()->route('admin.users.edit', $user)
            ->with('status', 'A password reset link has been sent to '.$user->email.'.');
    }

    /**
     * A forbidden role choice (not permitted by UserPolicy::assignRole, e.g.
     * granting Super Admin, or acting on your own account) is ignored, not
     * saved — the same "ignored, not a 422" convention as every other
     * status/permission-gated field in this app.
     */
    private function applyRole(UserRequest $request, User $user): void
    {
        if (! $request->filled('role_id')) {
            return;
        }

        $role = Role::find($request->validated('role_id'));
        if ($role && $request->user()->can('assignRole', [$user, $role])) {
            $user->role_id = $role->id;
        }
    }

    /**
     * Returns true (deactivated), false (reactivated) or null (no status
     * change, whether or not one was requested).
     */
    private function applyStatus(UserRequest $request, User $user): ?bool
    {
        if (! $request->filled('status')) {
            return null;
        }

        $target = UserStatus::from($request->validated('status'));
        if ($target === $user->status || ! $request->user()->can('deactivate', $user)) {
            return null;
        }

        $user->status = $target;

        return $target === UserStatus::Inactive;
    }

    private function sendPasswordResetLink(User $user): void
    {
        Password::broker()->sendResetLink(['email' => $user->email], function (User $u, #[\SensitiveParameter] string $token) {
            $u->sendPasswordResetNotification($token);
        });
    }

    /**
     * The Super Admin role is offered only to a Super Admin (mirrors the
     * after() check in UserRequest for a brand new account, and
     * UserPolicy::assignRole for an existing one).
     *
     * @return Collection<int, Role>
     */
    private function roleOptions()
    {
        return Role::query()
            ->when(! auth()->user()->isSuperAdmin(), fn ($q) => $q->where('slug', '!=', 'super-admin'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
