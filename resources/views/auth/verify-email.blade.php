<x-layouts.app title="Verify Email - CivicLens">
    <section class="mx-auto max-w-2xl rounded-lg border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900 sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-wider text-blue-800 dark:text-blue-300">Email verification</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight">Verify your email</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">Enter the six-digit code sent to your email, or use the signed verification link in the same message.</p>
        <form method="POST" action="{{ route('verification.otp') }}" class="mt-6 space-y-3">
            @csrf
            <label class="block text-sm font-semibold">Verification code
                <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="mt-1 w-full rounded-lg border px-3 py-2 text-slate-950" required>
            </label>
            @error('code')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <button type="submit" class="rounded-lg bg-blue-900 px-4 py-3 text-sm font-semibold text-white dark:bg-blue-300 dark:text-blue-950">Verify code</button>
        </form>
        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
            @csrf
            <button type="submit" class="rounded-lg bg-blue-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 dark:bg-blue-300 dark:text-blue-950 dark:hover:bg-blue-200">Resend verification email</button>
        </form>
    </section>
</x-layouts.app>
