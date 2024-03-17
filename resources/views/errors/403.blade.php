@extends('layouts.error')

@section('code', '403')
@section('title', __('Forbidden'))
@section('message', __($exception->getMessage() ?: 'Forbidden'))
