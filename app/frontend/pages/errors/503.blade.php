@extends('error')

@section('title', '503 — ' . config('app.name'))
@section('code', '503')
@section('icon', 'fa-solid fa-screwdriver-wrench')
@section('icon-variant', 'maintenance')
@section('heading', 'Under maintenance')
@section('message', "We're performing scheduled maintenance and will be back shortly. Thanks for your patience.")

@section('actions')
  <a class="btn btn--primary" href="{{ url('/') }}"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Try again</a>
@endsection
