@extends('layouts.app')
@section('title', 'Log in — Freedom Data')
@section('content')
<div class="mx-auto max-w-sm">
    <div class="mb-5 text-center">
        <span class="signal-bars" style="justify-content:center"><span class="bar lit"></span><span class="bar lit"></span><span class="bar"></span><span class="bar"></span></span>
        <h1 class="font-display mt-3 text-xl font-semibold">Welcome back</h1>
    </div>
    <form method="POST" action="/login" class="card space-y-3 p-5">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium">Phone number</label>
            <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="0241234567" class="form-input font-mono" required>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Password</label>
            <input type="password" name="password" placeholder="********"  class="form-input" required>
        </div>
        <button type="submit" class="btn-gold w-full">Log in</button>
        <p class="text-center text-sm text-ink-muted">Don't have an account? <a href="/register" class="text-gold-dark underline">Sign up</a></p>
    </form>
</div>
@endsection
