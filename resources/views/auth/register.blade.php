@extends('layouts.app')
@section('title', 'Create Account — Freedom Data')
@section('content')
<div class="mx-auto max-w-sm">
    <div class="mb-5 text-center">
        <span class="signal-bars" style="justify-content:center"><span class="bar lit"></span><span class="bar"></span><span class="bar"></span><span class="bar"></span></span>
        <h1 class="font-display mt-3 text-xl font-semibold">Create your account</h1>
    </div>
    <form method="POST" action="/register" class="card space-y-3 p-5">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium">Full name</label>
            <input type="text" name="name" value="{{ old('name') }}" class="form-input" required>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Phone number</label>
            <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="0241234567" class="form-input font-mono" required>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Email address</label>
            <input type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" class="form-input" required>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Password</label>
            <input type="password" name="password" class="form-input" required>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Confirm password</label>
            <input type="password" name="password_confirmation" class="form-input" required>
        </div>
        <button type="submit" class="btn-gold w-full">Sign up</button>
        <p class="text-center text-sm text-ink-muted">Already have an account? <a href="/login" class="text-gold-dark underline">Log in</a></p>
    </form>
</div>
@endsection
