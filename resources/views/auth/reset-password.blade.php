<x-layouts.app title="Reset Password - CivicLens">
    <section class="max-w-md">
        <h1 class="text-3xl font-bold">Reset Password</h1>
        <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <label class="block">
                <span>Email</span>
                <input name="email" type="email" value="{{ old('email', $request->email) }}" required class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
            </label>
            <label class="block">
                <span>Password</span>
                <input name="password" type="password" required class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
            </label>
            <label class="block">
                <span>Confirm Password</span>
                <input name="password_confirmation" type="password" required class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
            </label>
            <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Reset password</button>
        </form>
    </section>
</x-layouts.app>

