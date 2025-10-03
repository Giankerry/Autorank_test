@extends('layouts.profile-page-layout')

@section('title')
@if ($isOwnProfile)
Profile | Autorank
@else
{{ $user->name }}'s Profile | Autorank
@endif
@endsection

@section('content')
<div class="content-container">
    <div class="content">
        <div class="content-left-side">
            <div class="profile-img-container">
                <img src="{{ $user->avatar }}" alt="{{ $user->name }}'s profile picture">
            </div>
            <div class="separator-container">
                <h6>Basic Info</h6>
                <hr>
            </div>
            <div class="basic-info-container">
                <div id="copyToast" class="toast-container">
                    <p>Copied to clipboard!</p>
                </div>
                <div class="basic-info-fields">
                    <div class="basic-info">
                        <h3>Full Name</h3>
                        <h5 id="username">{{ $user->name }}</h5>
                    </div>
                    <div class="basic-info-action" title="Copy Instructor Name" onclick="copyInstructorsName();">
                        <i class="fa-regular fa-copy"></i>
                    </div>
                </div>
                <div class="basic-info-fields">
                    <div class="basic-info">
                        <h3>Instructor Number</h3>
                        <h5 id="instructorsNumber">{{ $user->instructor_number ?? 'TBC' }}</h5>
                    </div>
                    <div class="basic-info-action" title="Copy Instructor Number" onclick="copyInstructorNumber();">
                        <i class="fa-regular fa-copy"></i>
                    </div>
                </div>
                <div class="basic-info-fields">
                    <div class="basic-info">
                        <h3>Faculty Rank</h3>
                        <h5>{{ $user->faculty_rank ?? 'Unset (Please reach out to an Admin.)' }}</h5>
                    </div>
                    <div class="basic-info-action"></div>
                </div>
                <div class="basic-info-fields">
                    <div class="basic-info">
                        <h3>Email</h3>
                        <h5>{{ $user->email }}</h5>
                    </div>
                    @if (!$isOwnProfile)
                    <div class="basic-info-action">
                        <a href="mailto:{{ $user->email }}" title="Email Instructor">
                            <i class="fa-regular fa-envelope"></i>
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="content-right-side">
            <div class="credentials-and-experience-container">
                <div class="title">
                    <h1>Profile</h1>
                </div>
                <div class="progress-bars-container">
                    <div class="evaluation-progress-container">
                        <div class="subtitle">
                            <h4>Evaluation Progress</h4>
                            <h5>In progress</h5> {{-- this changes when the progress bar is full --}}
                        </div>
                        <div class="evaluation-progress-bar-container">
                            <div class="evaluation-progress"></div>
                            <div class="bar"></div>
                        </div>
                        <div class="evaluation-progress-bottom-note">
                            <h5>50%</h5> {{-- this would be dynamically generated based on the percentage --}}
                        </div>
                    </div>
                </div>
                @if ($isOwnProfile)
                <div class="apply-for-reranking-container">
                    <button id="start-evaluation-btn"
                    data-check-url="{{ route('instructor.evaluation.check') }}">
                        Start CCE Evaluation Process
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<form id="submit-evaluation-form" action="{{ route('instructor.evaluation.submit') }}" method="POST" style="display: none;">
    @csrf
</form>
@endsection

@push('page-scripts')
<script src="{{ asset('js/modal-scripts.js') }}"></script>
<script src="{{ asset('js/profile-page-scripts.js') }}"></script>
@endpush
