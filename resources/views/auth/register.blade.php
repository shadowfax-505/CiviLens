<x-layouts.app title="Register - CivicLens">
    <section class="grid gap-8 lg:grid-cols-[1fr_500px] lg:items-stretch">
        <div class="rounded-lg bg-blue-950 p-8 text-white shadow-xl shadow-slate-900/10 lg:p-10">
            <p class="text-sm font-semibold uppercase tracking-wider text-blue-200">CivicLens v1.0.0</p>
            <h1 class="mt-4 max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">Public data, procurement, and integrity signals in one accountable workspace.</h1>
            <p class="mt-5 max-w-xl text-base leading-7 text-blue-100">Create an operator account for auditable workflows, permission-aware records, and deterministic civic intelligence review.</p>
            <div class="mt-8 grid gap-3 text-sm font-semibold text-blue-50">
                <span class="rounded-lg bg-white/10 px-4 py-3">Audit-first activity history</span>
                <span class="rounded-lg bg-white/10 px-4 py-3">Explainable integrity indicators</span>
                <span class="rounded-lg bg-white/10 px-4 py-3">Public-safe transparency controls</span>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900 sm:p-8">
        <h2 class="text-2xl font-bold tracking-tight">Create account</h2>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Use a strong password to protect administrative and evidence-review workflows.</p>
        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-5">
            @csrf
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                <span>Name</span>
                <input name="name" value="{{ old('name') }}" required autocomplete="name" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-950 shadow-sm transition focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                @error('name')<span class="mt-1 block text-sm text-red-600 dark:text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                <span>Email</span>
                <input name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-950 shadow-sm transition focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                @error('email')<span class="mt-1 block text-sm text-red-600 dark:text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                <span>Password</span>
                <input name="password" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-950 shadow-sm transition focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                @error('password')<span class="mt-1 block text-sm text-red-600 dark:text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                <span>Confirm password</span>
                <input name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-950 shadow-sm transition focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </label>
            <button class="w-full rounded-lg bg-blue-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 dark:bg-blue-300 dark:text-blue-950 dark:hover:bg-blue-200">Register</button>
        </form>
        <p class="mt-6 text-center text-sm text-slate-600 dark:text-slate-300">Already registered? <a class="font-semibold text-blue-800 hover:text-blue-700 dark:text-blue-300" href="{{ route('login') }}">Sign in</a></p>
        </div>
    </section>
</x-layouts.app>
