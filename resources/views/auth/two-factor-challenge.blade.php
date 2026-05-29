@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Two-factor authentication
            </h2>
        </div>

        <form class="mt-8 space-y-6" action="{{ route('two-factor.login') }}" method="POST">
            @csrf

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4">
                    <div class="text-sm text-red-700">
                        @if ($errors->has('code'))
                            {{ $errors->first('code') }}
                        @else
                            {{ $errors->first('recovery_code') }}
                        @endif
                    </div>
                </div>
            @endif

            @if (session('status'))
                <div class="rounded-md bg-blue-50 p-4">
                    <div class="text-sm text-blue-700">{{ session('status') }}</div>
                </div>
            @endif

            <div id="code" class="space-y-4">
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">
                        Authentication code
                    </label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        inputmode="numeric"
                        placeholder="000000"
                        autocomplete="one-time-code"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    />
                </div>
            </div>

            <div id="recovery_code" class="space-y-4 hidden">
                <div>
                    <label for="recovery_code" class="block text-sm font-medium text-gray-700">
                        Recovery code
                    </label>
                    <input
                        id="recovery_code"
                        name="recovery_code"
                        type="text"
                        placeholder="00000000-0000"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    />
                </div>
            </div>

            <button
                type="submit"
                class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
                Verify
            </button>

            <div class="text-center">
                <button
                    type="button"
                    onclick="toggleRecoveryCode()"
                    class="text-sm text-blue-600 hover:text-blue-500"
                >
                    Use a recovery code instead
                </button>
            </div>
        </form>
    </div>

    <script>
        function toggleRecoveryCode() {
            const codeInput = document.getElementById('code');
            const recoveryCodeInput = document.getElementById('recovery_code');
            const codeDiv = document.getElementById('code');
            const recoveryCodeDiv = document.getElementById('recovery_code');

            codeDiv.classList.toggle('hidden');
            recoveryCodeDiv.classList.toggle('hidden');

            if (codeDiv.classList.contains('hidden')) {
                codeInput.removeAttribute('required');
                recoveryCodeInput.setAttribute('required', '');
            } else {
                codeInput.setAttribute('required', '');
                recoveryCodeInput.removeAttribute('required');
            }
        }
    </script>
</div>
@endsection
