@extends('error')

@section('title', '403 — ' . config('app.name'))
@section('code', '403')
@section('icon', 'fa-solid fa-lock')
@section('icon-variant', 'forbidden')
@section('heading', 'Access denied')
@section('message', "You don't have permission to view this page. If you believe this is a mistake, contact support.")

@section('actions')
  <a class="btn btn--primary" href="{{ url()->previous() }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Go back</a>
  <a class="btn btn--outline" href="{{ url('/') }}">Home</a>
@endsection
