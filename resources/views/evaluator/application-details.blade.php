@extends('layouts.view-all-layout')

@section('title', 'Application Details | Autorank')

@section('content')

<div class="header">
    <div class="header-text" >
        <h1>Applicant: {{ $application->user->name }}</h1>
        {{-- Updated to show the user's current rank as the concept of applying for a specific position was removed --}}
        <p class="text-muted">Current Rank: <strong style="font-weight: 550">{{ $application->user->rank->name ?? 'Not Set' }}</strong> | Submitted: <strong style="font-weight: 550">{{ $application->created_at->format('F d, Y') }}</strong></p>
    </div>
</div>

{{-- Main container for the KRA summary --}}
<div class="performance-metric-container">
    {{-- This table lists the fixed KRAs and their submission counts for this specific application --}}
    <table>
        <thead>
            <tr>
                <th>General Score</th>
                <th>Key Result Area</th>
                <th>Total Submissions</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            {{-- KRA I: Instruction --}}
            <tr>
                <td rowspan="4" style="text-align: center; vertical-align: middle; font-size: 2rem; font-weight: 500;">
                    @if($application->status == 'evaluated' && isset($application->final_score))
                        {{ number_format($application->final_score, 2) }}
                    @else
                        <span style="font-size: 1rem; font-weight: 400; color: #6c757d;">Not yet scored</span>
                    @endif
                </td>
                <td>KRA I: Instruction</td>
                <td>( <strong style="font-size: 1.2rem">{{ $application->instructions_count }}</strong> ) Submissions</td>
                <td>
                    @if($application->instructions_count > 0)
                        <a href="{{ route('evaluator.application.kra', ['application' => $application->id, 'kra_slug' => 'instruction']) }}" class="btn btn-primary">
                            <button>Score Submissions</button>
                        </a>
                    @else
                        <button class="btn btn-secondary" disabled>No Submissions</button>
                    @endif
                </td>
            </tr>

            {{-- KRA II: Research --}}
            <tr>
                <td>KRA II: Research</td>
                <td>( <strong style="font-size: 1.2rem">{{ $application->researches_count }}</strong> ) Submissions</td>
                <td>
                    @if($application->researches_count > 0)
                        <a href="{{ route('evaluator.application.kra', ['application' => $application->id, 'kra_slug' => 'research']) }}" class="btn btn-primary">
                            <button>Score Submissions</button>
                        </a>
                    @else
                        <button class="btn btn-secondary" disabled>No Submissions</button>
                    @endif
                </td>
            </tr>

            {{-- KRA III: Extension --}}
            <tr>
                <td>KRA III: Extension</td>
                <td>( <strong style="font-size: 1.2rem">{{ $application->extensions_count }}</strong> ) Submissions</td>
                <td>
                    @if($application->extensions_count > 0)
                        <a href="{{ route('evaluator.application.kra', ['application' => $application->id, 'kra_slug' => 'extension']) }}" class="btn btn-primary">
                            <button>Score Submissions</button>
                        </a>
                    @else
                        <button class="btn btn-secondary" disabled>No Submissions</button>
                    @endif
                </td>
            </tr>

            {{-- KRA IV: Professional Development --}}
            <tr>
                <td>KRA IV: Professional Development</td>
                <td>( <strong style="font-size: 1.2rem">{{ $application->professional_developments_count }}</strong> ) Submissions</td>
                <td>
                    @if($application->professional_developments_count > 0)
                        <a href="{{ route('evaluator.application.kra', ['application' => $application->id, 'kra_slug' => 'professional-development']) }}" class="btn btn-primary">
                            <button>Score Submissions</button>
                        </a>
                    @else
                        <button class="btn btn-secondary" disabled>No Submissions</button>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="load-more-container" style="margin-top: 20px;">
    <a href="{{ route('evaluator.applications.dashboard') }}" class="btn btn-secondary"><button>Back</button></a>
    {{-- Final Score Display & Calculation Button --}}
    <div class="final-score-container">
        @if($application->status == 'evaluated')
            <div class="score-display">
                <h2>Final Score: <span>{{ number_format($application->final_score, 2) }} / 100.00</span></h2>
                <p>This application has been scored and is awaiting review from the administrator.</p>
            </div>
        @else
            {{-- This form submits the request to calculate the final score --}}
            <form method="POST" action="{{ route('evaluator.application.calculate-score', $application->id) }}" onsubmit="return confirm('Are you sure you want to finalize and calculate the score? This action cannot be undone.');">
                @csrf
                <button type="submit" class="upload-new-button">
                    Calculate General Score
                </button>
            </form>
        @endif
    </div>
</div>

@endsection