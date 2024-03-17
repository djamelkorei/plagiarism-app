@extends('layouts.error')

@section('code', '503')
@section('title', __('Service Unavailable'))
@section('message', __($exception->getMessage() ?: 'Service Unavailable'))
