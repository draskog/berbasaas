@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Verify your email address
            </h2>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="rounded-md bg-green-50 p-4">
                <div class="text-sm text-green-700">
                    A new verification link has been sent to your email address.
                </div>
            </div>
        @endif

        <div class="rounded-md bg-blue-50 p-4">
            <div class="text-sm text-blue-700">
                Before proceeding, please check your email for a verification link.
            </div>
        </div>

        <form action="{{ route('verification.send') }}" method="POST">
            @csrf
            <button
                type="submit"
                class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
                Resend verification email
            </button>
        </form>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button
                type="submit"
                class="w-full flex justify-center py-2 px-4 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
                Sign out
            </button>
        </form>
    </div>
</div>
@endsection
