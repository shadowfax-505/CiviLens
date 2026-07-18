<x-layouts.app title="Login - CivicLens">
    <section class="mx-auto grid max-w-5xl gap-8 lg:grid-cols-[1fr_460px] lg:items-center">
        <div class="rounded-lg border border-slate-200 bg-white p-8 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm font-semibold uppercase tracking-wider text-blue-800 dark:text-blue-300">Secure workspace</p>
            <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">Sign in to CivicLens</h1>
            <p class="mt-4 text-base leading-7 text-slate-600 dark:text-slate-300">Access dashboards, procurement records, document evidence, and explainable integrity indicators with your assigned permissions.</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900 sm:p-8">
            @csrf
            <div class="space-y-5">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                <span>Email</span>
                <input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-950 shadow-sm transition focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                @error('email')<span class="mt-1 block text-sm text-red-600 dark:text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                <span>Password</span>
                <input name="password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-950 shadow-sm transition focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                @error('password')<span class="mt-1 block text-sm text-red-600 dark:text-red-300">{{ $message }}</span>@enderror
            </label>
            <div class="text-right">
                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-blue-800 hover:text-blue-700 dark:text-blue-300">Forgot password?</a>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input name="remember" type="checkbox" class="rounded border-slate-300 text-blue-900 focus:ring-blue-700">
                <span>Remember me</span>
            </label>
            <button type="submit" class="w-full rounded-lg bg-blue-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 dark:bg-blue-300 dark:text-blue-950 dark:hover:bg-blue-200">Login</button>
            </div>
        </form>
    </section>
</x-layouts.app>
