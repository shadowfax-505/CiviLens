<x-layouts.app title="Register - CivicLens">
    <section class="max-w-md">
        <h1 class="text-3xl font-bold">Create Account</h1>
        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
            @csrf
            <label class="block">
                <span>Name</span>
                <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block">
                <span>Email</span>
                <input name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @error('email')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block">
                <span>Password</span>
                <input name="password" type="password" required class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @error('password')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block">
                <span>Confirm Password</span>
                <input name="password_confirmation" type="password" required class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
            </label>
            <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Register</button>
        </form>
    </section>
</x-layouts.app>

