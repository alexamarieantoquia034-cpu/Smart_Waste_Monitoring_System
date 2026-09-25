<x-app-layout>
    <div class="sw-profile">
        <div class="sw-page-head">
            <p class="sw-page-head__eyebrow">Account</p>
            <h1 class="sw-page-head__title">Profile Settings</h1>
            <p class="sw-page-head__lead">Update your account details and keep your credentials secure.</p>
        </div>

        <div class="sw-card">
            <div class="sw-card__body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="sw-card">
            <div class="sw-card__body">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="sw-card sw-card--danger">
            <div class="sw-card__body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
