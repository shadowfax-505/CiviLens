<x-layouts.app title="Confirm Password - CivicLens">
    <section class="mx-auto max-w-xl rounded-lg border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900 sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-wider text-blue-800 dark:text-blue-300">Protected action</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight">Confirm password</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">Re-enter your password before continuing to this sensitive workflow.</p>
        <form method="POST" action="{{ route('password.confirm') }}" class="mt-6 space-y-5">
            @csrf
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                <span>Password</span>
                <input name="password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-950 shadow-sm transition focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                @error('password')<span class="mt-1 block text-sm text-red-600 dark:text-red-300">{{ $message }}</span>@enderror
            </label>
            <button type="submit" class="w-full rounded-lg bg-blue-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 dark:bg-blue-300 dark:text-blue-950 dark:hover:bg-blue-200">Confirm</button>
        </form>
    </section>
</x-layouts.app>
