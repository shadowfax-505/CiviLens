<x-layouts.app title="Verify Email - CivicLens">
    <section class="max-w-xl">
        <h1 class="text-3xl font-bold">Verify Email</h1>
        <p class="mt-2 text-slate-600 dark:text-slate-300">Please verify your email address before continuing.</p>
        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
            @csrf
            <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Resend verification email</button>
        </form>
    </section>
</x-layouts.app>

