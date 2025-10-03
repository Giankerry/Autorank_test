<?php

namespace App\Services;

use App\Models\Application;
use Illuminate\Support\Facades\Log;

class AHPService
{
    // DBM-CHED (2022) KRA Category Point Caps
    protected const KRA_CAPS = [
        'kra1' => [ // Instruction
            'total' => 40, // Excludes teaching effectiveness (QCE)
            'sub_caps' => [
                'instructional_materials' => 30,  // Textbooks, modules, curriculum dev
                'mentorship_services' => 10,      // Thesis advising, student coaching
            ],
            // Note: Teaching effectiveness (student/peer eval) is NOT included here (QCE)
        ],

        'kra2' => [ // Research, Invention, Creative Work
            'total' => 100,
            'sub_caps' => [
                // These are sources of outputs; total capped at 100
                'research_outputs' => 100,        // Publications, papers
                'inventions' => 100,              // Patents, inventions
                'creative_works' => 100,          // Artistic/creative outputs
            ],
            // Note: The combined total of these must NOT exceed 100 points
        ],

        'kra3' => [ // Extension, Service, Outreach
            'total' => 100,
            'sub_caps' => [
                'service_to_institution' => 30,   // Committee work, admin tasks
                'service_to_community' => 50,     // Community outreach, trainings
                'extension_involvement' => 20,    // Extension projects, programs
            ],
            'bonus' => 20, // Optional bonus for admin/leadership roles (added on top of 100)
        ],

        'kra4' => [ // Professional Development, Achievements
            'total' => 100,
            'sub_caps' => [
                'professional_organizations' => 20,   // Memberships, offices held
                'continuing_development' => 60,       // Trainings, seminars, capacity building
                'awards_and_recognitions' => 20,      // Honors, citations
            ],
            'new_hire_bonus' => 20, // Bonus for newly hired faculty (e.g. relevant industry exp)
        ],
    ];

    protected const RANK_THRESHOLDS = [
        'Professor V' => 595,
        'Professor IV' => 562,
        'Professor III' => 529,
        'Professor II' => 496,
        'Professor I' => 463,
        'Associate Professor V' => 430,
        'Associate Professor IV' => 397,
        'Associate Professor III' => 364,
        'Associate Professor II' => 331,
        'Associate Professor I' => 298,
        'Assistant Professor IV' => 265,
        'Assistant Professor III' => 232,
        'Assistant Professor II' => 199,
        'Assistant Professor I' => 166,
        'Instructor III' => 133,
        'Instructor II' => 100,
        'Instructor I' => 66,
    ];

    protected const RANK_KRA_WEIGHTS = [
        'Instructor' => [
            'kra1' => 0.80,
            'kra2' => 0.10,
            'kra3' => 0.05,
            'kra4' => 0.05,
        ],
        'Assistant Professor' => [
            'kra1' => 0.60,
            'kra2' => 0.20,
            'kra3' => 0.10,
            'kra4' => 0.10,
        ],
        'Associate Professor' => [
            'kra1' => 0.40,
            'kra2' => 0.30,
            'kra3' => 0.15,
            'kra4' => 0.15,
        ],
        'Professor' => [
            'kra1' => 0.30,
            'kra2' => 0.40,
            'kra3' => 0.15,
            'kra4' => 0.15,
        ],
    ];

    /**
     * Calculates the final weighted score for an application based on the user's rank.
     *
     * @param Application $application The application with pre-aggregated KRA scores.
     * @param string $rankCategory The general rank category (e.g., 'Professor', 'Instructor').
     * @return float The final, weighted CCE score.
     */
    public function calculateFinalScore(Application $application, string $rankCategory): float
    {
        // Step 1: Get the raw KRA scores from the application model.
        $rawScores = [
            'kra1' => $application->kra1_score ?? 0,
            'kra2' => $application->kra2_score ?? 0,
            'kra3' => $application->kra3_score ?? 0,
            'kra4' => $application->kra4_score ?? 0,
        ];

        // Step 2: Apply the DBM-CHED caps to each KRA total score.
        $cappedScores = [
            'kra1' => min($rawScores['kra1'], self::KRA_CAPS['kra1']['total']),
            'kra2' => min($rawScores['kra2'], self::KRA_CAPS['kra2']['total']),
            'kra3' => min($rawScores['kra3'], self::KRA_CAPS['kra3']['total']),
            'kra4' => min($rawScores['kra4'], self::KRA_CAPS['kra4']['total']),
        ];

        // Step 3: Get the AHP weights for the applicant's rank category.
        if (!isset(self::RANK_KRA_WEIGHTS[$rankCategory])) {
            Log::error("AHP Calculation Failed: No weights found for rank category '{$rankCategory}' for Application ID {$application->id}");
            return 0.0;
        }
        $weights = self::RANK_KRA_WEIGHTS[$rankCategory];

        // Step 4: Calculate the final weighted score.
        $finalScore = 0;
        $finalScore += $cappedScores['kra1'] * $weights['kra1'];
        $finalScore += $cappedScores['kra2'] * $weights['kra2'];
        $finalScore += $cappedScores['kra3'] * $weights['kra3'];
        $finalScore += $cappedScores['kra4'] * $weights['kra4'];

        return (float) $finalScore;
    }

    /**
     * Determines the highest attainable rank based on a given CCE score.
     *
     * @param float $score The final CCE score.
     * @return string The name of the highest rank achieved.
     */
    public function getRankFromScore(float $score): string
    {
        foreach (self::RANK_THRESHOLDS as $rank => $threshold) {
            if ($score >= $threshold) {
                return $rank;
            }
        }

        return "Below 'Instructor I' Threshold";
    }
}
