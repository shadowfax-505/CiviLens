<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Search\Searchable;
use App\Http\Controllers\Controller;
use App\Services\Search\KnowledgeGraphService;
use App\Services\Search\SearchRegistry;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SearchKnowledgeController extends Controller
{
    public function __invoke(Request $request, string $module, int $id, SearchRegistry $registry, KnowledgeGraphService $graph): View
    {
        $class = $registry->classForModule($module);
        abort_unless($class !== null, 404);

        $entity = $class::query()->findOrFail($id);
        abort_unless($entity instanceof Searchable && $entity instanceof Model, 404);

        $user = AuthenticatedUser::from($request);
        abort_unless(
            $user->hasRole(config('civiclens.roles.admin'))
            || Gate::forUser($user)->allows('view', $entity)
            || Gate::forUser($user)->allows('viewAny', $entity::class),
            403,
        );

        return view('admin.search.knowledge', [
            'graph' => $graph->for($entity, $user),
        ]);
    }
}
