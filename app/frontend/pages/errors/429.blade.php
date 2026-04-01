@extends('error')

@section('title', '429 — ' . config('app.name'))
@section('code', '429')
@section('icon', 'fa-solid fa-gauge-high')
@section('icon-variant', 'throttle')
@section('heading', 'Too many requests')
@section('message', "You've made too many requests in a short period. Please wait a moment and try again.")

@section('actions')
  <a class="btn btn--primary" href="{{ url()->previous() }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Go back</a>
  <a class="btn btn--outline" href="{{ url('/') }}">Home</a>
@endsection
