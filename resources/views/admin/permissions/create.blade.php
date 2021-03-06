@extends('admin.layouts.app')

@section('title')
    @lang('menus.permissions') | @parent
@endsection

@section('content')

    @component('admin.components.form_create', [
        'formAction' => route('admin.permissions.store'),
        'redirectUrl' => route('admin.permissions.index')
    ])

        @include('admin.permissions._form')

    @endcomponent

@endsection