<?php

namespace Tests\Support;

use App\Models\Project;
use Illuminate\Database\Seeder;

class BrowserVisibilitySeeder extends Seeder
{
    public function run(): void
    {
        Project::query()
            ->where('project_code', 'CL-DEMO-005')
            ->update(['is_public' => false]);
    }
}
