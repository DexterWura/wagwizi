@extends('error')

@section('title', '500 — ' . config('app.name'))
@section('code', '500')
@section('icon', 'fa-solid fa-bolt')
@section('icon-variant', 'server')
@section('heading', 'Something went wrong')
@section('message', "We hit an unexpected problem on our end. Our team has been notified. Please try again in a few moments.")

@section('actions')
  <a class="btn btn--primary" href="{{ url('/') }}"><i class="fa-solid fa-house" aria-hidden="true"></i> Go home</a>
  <a class="btn btn--outline" href="{{ url()->previous() }}">Go back</a>
@endsection
