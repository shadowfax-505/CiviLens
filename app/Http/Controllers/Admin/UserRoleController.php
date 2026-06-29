<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Models\User;
use App\Services\Identity\UserAdministrationService;
use Illuminate\Http\RedirectResponse;

class UserRoleController extends Controller
{
    public function update(UpdateUserRolesRequest $request, User $user, UserAdministrationService $service): RedirectResponse
    {
        $service->syncRoles($user, $request->array('roles'), $request->user(), $request);

        return back()->with('status', 'user-roles-updated');
    }
}
