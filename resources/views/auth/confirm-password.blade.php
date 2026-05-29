@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Confirm your password
            </h2>
        </div>

        <div class="rounded-md bg-blue-50 p-4">
            <div class="text-sm text-blue-700">
                This is a secure area of the application. Please confirm your password before continuing.
            </div>
        </div>

        <form class="mt-8 space-y-6" action="{{ route('password.confirm') }}" method="POST">
            @csrf

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4">
                    <div class="text-sm text-red-700">{{ $errors->first() }}</div>
                </div>
            @endif

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                />
            </div>

            <button
                type="submit"
                class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
                Confirm
            </button>
        </form>
    </div>
</div>
@endsection
