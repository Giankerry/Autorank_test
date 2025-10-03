<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Application;

class ApplyController extends Controller
{
    /**
     * Performs a pre-check to see if the user's rank is set and if they have submissions for all KRAs.
     * This is intended to be called via AJAX.
     */
    public function checkSubmissions(Request $request)
    {
        $user = Auth::user();

        // Pre-Check: Validate that the user has a rank assigned.
        if (is_null($user->faculty_rank) || trim($user->faculty_rank) === '' || trim($user->faculty_rank) === 'Unset') {
            return response()->json([
                'success' => false,
                'error_type' => 'rank_missing',
                'message' => 'You do not have a faculty rank assigned. Please contact the system administrator to have your rank validated and set before submitting your CCE documents.'
            ]);
        }

        $missing = [];

        // Find the user's current draft application
        $draftApplication = $user->applications()->where('status', 'draft')->first();

        if (!$draftApplication) {
            // If there's no draft application, it means they haven't uploaded anything yet for this cycle.
            $missing = [
                ['name' => 'KRA I: Instruction', 'route' => route('instructor.instructional-page')],
                ['name' => 'KRA II: Research', 'route' => route('instructor.research-page')],
                ['name' => 'KRA III: Extension', 'route' => route('instructor.extension-page')],
                ['name' => 'KRA IV: Professional Development', 'route' => route('instructor.professional-development-page')],
            ];
            return response()->json(['success' => false, 'missing' => $missing]);
        }

        // Check for submissions linked to the specific draft application
        if ($draftApplication->instructions()->count() === 0) {
            $missing[] = ['name' => 'KRA I: Instruction', 'route' => route('instructor.instructional-page')];
        }
        if ($draftApplication->researches()->count() === 0) {
            $missing[] = ['name' => 'KRA II: Research', 'route' => route('instructor.research-page')];
        }
        if ($draftApplication->extensions()->count() === 0) {
            $missing[] = ['name' => 'KRA III: Extension', 'route' => route('instructor.extension-page')];
        }
        if ($draftApplication->professionalDevelopments()->count() === 0) {
            $missing[] = ['name' => 'KRA IV: Professional Development', 'route' => route('instructor.professional-development-page')];
        }

        if (empty($missing)) {
            return response()->json(['success' => true, 'message' => 'Application Submitted!'], 201);
        } else {
            return response()->json(['success' => false, 'missing' => $missing]);
        }
    }

    /**
     * Submits the user's draft application for evaluation.
     */
    public function submitEvaluation(Request $request)
    {
        $user = Auth::user();

        // Server-Side Gate: Final validation to ensure user has a rank.
        // This acts as a safeguard in case the frontend check is bypassed.
        if (is_null($user->faculty_rank) || trim($user->faculty_rank) === '' || trim($user->faculty_rank) === 'Unset') {
            return redirect()->route('profile-page')->with('error', 'Submission Denied: You do not have a faculty rank assigned. Please contact the system administrator to have your rank validated and set before submitting your CCE documents.');
        }

        // Check for an existing application that is already pending evaluation to prevent duplicates
        if ($user->applications()->where('status', 'pending evaluation')->exists()) {
            return redirect()->route('profile-page')->with('error', 'You already have an application pending evaluation.');
        }

        // Find the user's draft application
        $draftApplication = $user->applications()->where('status', 'draft')->first();

        if (!$draftApplication) {
            return redirect()->route('profile-page')->with('error', 'No draft application found to submit.');
        }

        // Update the status of the draft application to 'pending evaluation'
        $draftApplication->status = 'pending evaluation';
        $draftApplication->save();

        return redirect()->route('profile-page')->with('success', 'Your CCE documents have been successfully submitted for evaluation!');
    }
}
