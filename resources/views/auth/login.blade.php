<x-layouts.app title="Login - CivicLens">
    <section class="max-w-md">
        <h1 class="text-3xl font-bold">Login</h1>
        <p class="mt-2 text-slate-600 dark:text-slate-300">Access the CivicLens dashboard.</p>

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
            @csrf
            <label class="block">
                <span>Email</span>
                <input name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @error('email')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block">
                <span>Password</span>
                <input name="password" type="password" required class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @error('password')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="flex items-center gap-2">
                <input name="remember" type="checkbox">
                <span>Remember me</span>
            </label>
            <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Login</button>
        </form>
    </section>
</x-layouts.app>

