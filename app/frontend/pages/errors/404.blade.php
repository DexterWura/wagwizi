@extends('error')

@section('title', '404 — ' . config('app.name'))
@section('code', '404')
@section('icon', 'fa-solid fa-magnifying-glass')
@section('icon-variant', 'not-found')
@section('heading', 'Page not found')
@section('message', "The page you're looking for doesn't exist or has been moved. Check the URL or head back to familiar ground.")

@section('actions')
  <a class="btn btn--primary" href="{{ url('/') }}"><i class="fa-solid fa-house" aria-hidden="true"></i> Go home</a>
  <a class="btn btn--outline" href="{{ url()->previous() }}">Go back</a>
@endsection
