<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Admin\UpdateUserLockRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Identity\UserAdministrationService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', User::class) === true, 403);

        return view('admin.users.create', [
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function store(CreateUserRequest $request, UserAdministrationService $service): RedirectResponse
    {
        $user = $service->createUser(
            $request->validated(),
            $request->validated('roles', []),
            AuthenticatedUser::from($request),
            $request,
        );

        return redirect()->route('admin.users.show', $user)->with('status', 'user-created');
    }

    public function show(User $user): View
    {
        abort_unless(request()->user()?->can('view', $user) === true, 403);

        $user->load('roles.permissions');

        return view('admin.users.show', [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('viewAny', User::class) === true, 403);

        $sort = in_array($request->query('sort'), ['name', 'email', 'created_at'], true)
            ? $request->query('sort')
            : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $users = User::query()
            ->with('roles')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status') === 'active', fn ($query) => $query->where('is_active', true)->whereNull('locked_at'))
            ->when($request->query('status') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($request->query('status') === 'locked', fn ($query) => $query->whereNotNull('locked_at'))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user, UserAdministrationService $service): RedirectResponse
    {
        $service->updateStatus($user, $request->boolean('is_active'), AuthenticatedUser::from($request), $request);

        return back()->with('status', 'user-status-updated');
    }

    public function updateLock(UpdateUserLockRequest $request, User $user, UserAdministrationService $service): RedirectResponse
    {
        $service->updateLock($user, $request->boolean('locked'), AuthenticatedUser::from($request), $request);

        return back()->with('status', 'user-lock-updated');
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user, UserAdministrationService $service): RedirectResponse
    {
        $service->setTemporaryPassword($user, $request->string('password'), AuthenticatedUser::from($request), $request);

        return back()->with('status', 'user-password-reset');
    }
}
