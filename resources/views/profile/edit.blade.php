@extends('layouts.admin')

@section('title', 'My Account')

@section('content')
    <div class="ui-page">
        <x-ui.page-header title="My Account"
            subtitle="Update your profile information, change your password, or delete your account." />

        <div class="grid gap-5 lg:grid-cols-2">
            <div class="ui-card ui-card-body">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="space-y-5">
                <div class="ui-card ui-card-body">
                    <div class="max-w-xl">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>

                <div class="ui-card ui-card-body">
                    <div class="max-w-xl">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
