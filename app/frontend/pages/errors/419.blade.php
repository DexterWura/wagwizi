@extends('error')

@section('title', '419 — ' . config('app.name'))
@section('code', '419')
@section('icon', 'fa-solid fa-clock-rotate-left')
@section('icon-variant', 'expired')
@section('heading', 'Page expired')
@section('message', 'Your session has timed out. Please refresh the page and try again.')

@section('actions')
  <a class="btn btn--primary" href="{{ url()->current() }}"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Refresh</a>
  <a class="btn btn--outline" href="{{ route('login') }}">Sign in</a>
@endsection
