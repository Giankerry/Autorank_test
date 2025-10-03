<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRolesRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\DataSearchService;

class UserController extends Controller
{
    /**
     * Display user table with search and "load more" functionality.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Services\DataSearchService $searchService
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request, DataSearchService $searchService)
    {
        $perPage = 5;

        // Decide whether we’re showing roles or faculty ranks
        $action = $request->input('action', 'manage-roles'); // default to roles

        // Base query
        if ($action === 'manage-faculty-rank') {
            // Only instructors for faculty rank management
            $query = User::role('user')->orderBy('created_at', 'desc');
            $searchableColumns = ['name', 'email'];
        } else {
            // All users for role management
            $query = User::with('roles.permissions')->orderBy('created_at', 'desc');
            $searchableColumns = ['name', 'email', 'id'];
        }

        $searchTerm = $request->input('search');
        $searchService->applySearch($query, $searchTerm, $searchableColumns);

        // AJAX pagination
        if ($request->ajax()) {
            $offset = $request->input('offset', 0);
            $users = (clone $query)->skip($offset)->take($perPage)->get();

            if ($action === 'manage-faculty-rank') {
                $facultyRanks = $this->getFacultyRanks();
                $html = '';
                foreach ($users as $user) {
                    $html .= view('partials._faculty_rank_table_row', compact('user', 'facultyRanks'))->render();
                }
            } else {
                $html = '';
                foreach ($users as $user) {
                    $html .= view('partials._user_table_row', compact('user'))->render();
                }
            }

            $totalUsersMatchingSearch = (clone $query)->count();
            $hasMore = ($offset + $perPage) < $totalUsersMatchingSearch;

            return response()->json([
                'html' => $html,
                'hasMore' => $hasMore,
                'nextOffset' => $offset + $perPage
            ]);
        }

        // Initial load
        $totalUsersMatchingSearch = (clone $query)->count();
        $users = $query->take($perPage)->get();
        $allRoles = Role::orderBy('rank', 'asc')->get();
        $facultyRanks = $this->getFacultyRanks();
        $initialHasMore = ($perPage < $totalUsersMatchingSearch);

        return view('admin.manage-users', compact(
            'users',
            'allRoles',
            'facultyRanks',
            'initialHasMore',
            'perPage'
        ));
    }


    /**
     * Update the specified user's roles in storage via AJAX.
     *
     * @param  \App\Http\Requests\UpdateUserRolesRequest  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRoles(UpdateUserRolesRequest $request, User $user)
    {
        /** @var \App\Models\User $loggedInUser */
        $loggedInUser = Auth::user();
        $assignedRoleIds = $request->validated()['roles'];

        // Get the ID of the specific role being assigned
        $targetRoleId = $assignedRoleIds[0] ?? null;

        // Retrieve the Role model for the target role
        $targetRole = null;
        if ($targetRoleId) {
            $targetRole = Role::findById($targetRoleId);
        }

        // Defensive check
        if (!$targetRole) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role selected for assignment.',
            ], 400); // Bad Request
        }

        // Retrieve the specific role objects for comparison
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $adminRole = Role::where('name', 'admin')->first();
        $userRole = Role::where('name', 'user')->first();

        // --- START: HIERARCHY-BASED AUTHORIZATION ---
        // A. Super Admin's role cannot be changed by anyone.
        if ($user->hasRole('super_admin')) {
            return response()->json([
                'message' => 'Cannot change the role of a Super Admin.',
            ], 403); // Forbidden
        }

        // B. General Authorization is handled by the UpdateUserRolesRequest

        // C. Specific Hierarchy Restrictions
        if ($loggedInUser->hasRole('admin') && !$loggedInUser->hasRole('super_admin')) {
            if ($superAdminRole && $targetRole->id === $superAdminRole->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to assign the Super Admin role.',
                ], 403); // Forbidden
            }
            if ($targetRole->id !== $adminRole->id && $targetRole->id !== $userRole->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'As an Admin, you can only assign "Admin" or "User" roles.',
                ], 403); // Forbidden
            }
            if ($loggedInUser->id !== $user->id && $user->hasRole('admin')) {
                return response()->json([
                    'message' => 'You cannot change the role of another Admin.',
                ], 403); // Forbidden
            }
            if ($loggedInUser->id === $user->id && $targetRole->id !== $adminRole->id) {
                return response()->json([
                    'message' => 'You are an Admin. You cannot demote yourself. A Super Admin must change your role.',
                ], 403); // Forbidden
            }
        }
        // --- END: HIERARCHY-BASED AUTHORIZATION ---

        // Sync (update) the user's roles
        $user->syncRoles($assignedRoleIds);

        // Update role assignment timestamp and assignee
        $user->role_assigned_at = Carbon::now();
        $user->role_assigned_by = $loggedInUser->email;
        $user->save();

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        if (Auth::check() && Auth::id() === $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'success' => true,
                'message' => 'Your role has been updated. Please sign in again.',
                'redirect_url' => route('signin-page'),
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'User\'s role updated successfully!',
            'newRolesHtml' => view('partials._roles_badge', ['user' => $user->fresh()])->render(),
            'newRoleAssignedAt' => $user->role_assigned_at->timezone('Asia/Manila')->format('m/d/y H:i'),
            'newRoleAssignedBy' => $user->role_assigned_by,
            'newRoleId' => $targetRole->id,
            'newRoleName' => \Illuminate\Support\Str::title(str_replace('_', ' ', $targetRole->name)),
        ]);
    }

    /**
     * Display the specified user's profile.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        // Load roles and permissions for the profile user
        $user->load('roles.permissions');

        // Determine if the currently authenticated user is viewing their own profile
        $isOwnProfile = (Auth::check() && Auth::id() === $user->id);

        // Return the existing 'profile-page' view, passing both the target user
        // and the flag indicating if it's the authenticated user's own profile.
        return view('profile-page', [
            'user' => $user,
            'isOwnProfile' => $isOwnProfile,
        ]);
    }

    /**
     * Update the specified user's faculty rank.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateFacultyRank(Request $request, User $user)
    {
        $request->validate([
            'faculty_rank' => 'required|string|in:' . implode(',', $this->getFacultyRanks()),
        ]);

        /** @var \App\Models\User $loggedInUser */
        $loggedInUser = Auth::user();

        $user->faculty_rank = $request->faculty_rank;
        $user->rank_assigned_at = Carbon::now();
        $user->rank_assigned_by = $loggedInUser->email;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Faculty rank updated successfully.',
            'newFacultyRank' => $user->faculty_rank,
            'newRankAssignedAt' => $user->rank_assigned_at->timezone('Asia/Manila')->format('m/d/y H:i'),
            'newRankAssignedBy' => $user->rank_assigned_by,
        ]);
    }


    /**
     * Get the list of faculty ranks.
     *
     * @return array
     */
    private function getFacultyRanks()
    {
        return [
            'Instructor I',
            'Instructor II',
            'Instructor III',
            'Assistant Professor I',
            'Assistant Professor II',
            'Assistant Professor III',
            'Assistant Professor IV',
            'Associate Professor I',
            'Associate Professor II',
            'Associate Professor III',
            'Associate Professor IV',
            'Associate Professor V',
            'Professor I',
            'Professor II',
            'Professor III',
            'Professor IV',
            'Professor V',
            'Professor VI',
        ];
    }
}
