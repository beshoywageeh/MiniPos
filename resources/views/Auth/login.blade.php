<x-layout>
    <div class="min-h-screen bg-slate-50 lg:grid lg:grid-cols-2">

        {{-- Brand Section --}}
        <section
            class="relative hidden overflow-hidden bg-slate-950 px-12 py-14 text-white lg:flex lg:flex-col lg:justify-between">

            <div class="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl"></div>
            <div class="absolute -bottom-32 -left-32 h-96 w-96 rounded-full bg-blue-500/20 blur-3xl"></div>

            <a href="/" class="relative flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500 text-xl font-bold">
                    M
                </div>
                <span class="text-xl font-bold tracking-tight">Mini POS</span>
            </a>

            <div class="relative max-w-lg">
                <span
                    class="mb-5 inline-flex rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-indigo-300">
                    Inventory & Sales Management
                </span>

                <h1 class="text-5xl font-bold leading-tight tracking-tight">
                    Welcome back.
                    <span class="text-indigo-400">Your business awaits.</span>
                </h1>

                <p class="mt-6 text-lg leading-8 text-slate-400">
                    Keep your business organized, track your inventory,
                    and manage your sales from one simple dashboard.
                </p>

                <div class="mt-10 space-y-5">
                    @foreach (['Monitor your sales and revenue', 'Keep track of your inventory', 'Manage your business with confidence'] as $feature)
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-500/20 text-indigo-400">
                                ✓
                            </span>
                            <span class="text-sm text-slate-300">{{ $feature }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="relative text-sm text-slate-500">
                © {{ date('Y') }} Mini POS. All rights reserved.
            </p>
        </section>

        {{-- Login Form --}}
        <section class="flex min-h-screen items-center justify-center px-5 py-12 sm:px-10">

            <div class="w-full max-w-md">

                {{-- Mobile Brand --}}
                <a href="/" class="mb-10 flex items-center justify-center gap-3 lg:hidden">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-lg font-bold text-white">
                        M
                    </div>
                    <span class="text-xl font-bold text-slate-900">Mini POS</span>
                </a>

                <div class="mb-8">
                    <p class="mb-3 text-sm font-semibold text-indigo-600">
                        WELCOME BACK
                    </p>

                    <h2 class="text-3xl font-bold tracking-tight text-slate-900">
                        Sign in to your account
                    </h2>

                    <p class="mt-3 text-sm leading-6 text-slate-500">
                        Enter your credentials to access your dashboard.
                    </p>
                </div>

                <form action="/login" method="POST" class="space-y-5">
                    @csrf

                    {{-- Email --}}
                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-slate-700">
                            {{ __('auth.email') }}
                        </label>

                        <input id="email" name="email" type="email" value="{{ old('email') }}"
                            autocomplete="email" placeholder="name@example.com" required autofocus
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 @error('email') border-red-400 focus:border-red-500 focus:ring-red-500/10 @enderror">

                        @error('email')
                            <p class="mt-2 text-xs font-medium text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div x-data="{ show: false }">

                        <div class="mb-2 flex items-center justify-between">
                            <label for="password" class="block text-sm font-medium text-slate-700">

                            </label>

                            <a href="#" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                                Forgot password?
                            </a>
                        </div>

                        <div class="relative">
                            <input id="password" name="password" :type="show ? 'text' : 'password'"
                                autocomplete="current-password" placeholder="Enter your password" required
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 pr-16 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 @error('password') border-red-400 focus:border-red-500 focus:ring-red-500/10 @enderror">

                            <button type="button" @click="show = !show"
                                class="absolute inset-y-0 right-4 text-xs font-semibold text-slate-500 hover:text-indigo-600">
                                <span x-text="show ? 'Hide' : 'Show'"></span>
                            </button>
                        </div>

                        @error('password')
                            <p class="mt-2 text-xs font-medium text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Submit --}}
                    <button type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:-translate-y-0.5 hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-500/20">
                        {{ __('auth.submit') }}
                        <span aria-hidden="true">→</span>
                    </button>
                </form>

                <p class="mt-8 text-center text-sm text-slate-500">
                    {{ __('auth.noaccount') }}
                    <a href="/register" class="font-semibold text-indigo-600 hover:text-indigo-700">
                        {{ __('auth.createaccount') }}
                    </a>
                </p>

                <p class="mt-10 text-center text-xs text-slate-400">
                    © {{ date('Y') }} {{ __('auth.msg') }}.
                </p>

            </div>
        </section>

    </div>
</x-layout>
