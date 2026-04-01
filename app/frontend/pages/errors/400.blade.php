@extends('error')

@section('title', '400 — ' . config('app.name'))
@section('code', '400')
@section('icon', 'fa-solid fa-circle-xmark')
@section('icon-variant', 'warning')
@section('heading', 'Bad request')
@section('message', "We couldn't understand that request. Please check the URL or form data and try again.")

@section('actions')
  <a class="btn btn--primary" href="{{ url()->previous() }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Go back</a>
  <a class="btn btn--outline" href="{{ url('/') }}">Home</a>
@endsection
