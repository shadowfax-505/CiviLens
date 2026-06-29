<x-layouts.app title="Forgot Password - CivicLens">
    <section class="max-w-md">
        <h1 class="text-3xl font-bold">Forgot Password</h1>
        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
            @csrf
            <label class="block">
                <span>Email</span>
                <input name="email" type="email" required class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @error('email')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Send reset link</button>
        </form>
    </section>
</x-layouts.app>

