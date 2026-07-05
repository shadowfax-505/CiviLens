<x-layouts.app title="Verify Email - CivicLens">
    <section class="mx-auto max-w-2xl rounded-lg border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900 sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-wider text-blue-800 dark:text-blue-300">Email verification</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight">Verify your email</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">Please verify your email address before continuing to CivicLens dashboards and evidence workflows.</p>
        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
            @csrf
            <button class="rounded-lg bg-blue-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 dark:bg-blue-300 dark:text-blue-950 dark:hover:bg-blue-200">Resend verification email</button>
        </form>
    </section>
</x-layouts.app>
