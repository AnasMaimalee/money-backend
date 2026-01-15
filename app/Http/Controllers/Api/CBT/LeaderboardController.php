<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class LeaderboardController extends Controller
{
    /**
     * Get top 10 leaderboard including current user's position (for regular users)
     */
    public function selfRank(Request $request)
    {
        $user = $request->user();

        abort_unless($user->hasRole('user'), 403, 'Only regular users can access this');

        // Get top 10 users who actually took exams (including current user if qualified)
        $topUsers = User::where('role', 'user')
            ->whereHas('exams')                           // must have taken at least 1 exam
            ->withSum('exams', 'total_score')
            ->orderByDesc('exams_sum_total_score')
            ->take(10)
            ->get(['id', 'name', 'email', 'phone', 'state']);

        // Find current user's rank position
        $myRank = null;
        $myTotalScore = 0;

        foreach ($topUsers as $index => $u) {
            if ($u->id === $user->id) {
                $myRank = $index + 1;
                $myTotalScore = (int) $u->exams_sum_total_score;
                break;
            }
        }

        // If current user is not in top 10, still show their score & approximate rank
        if (!$myRank && $user->exams()->exists()) {
            $myTotalScore = (int) $user->exams()->sum('total_score');

            $usersAboveMe = User::where('role', 'user')
                ->whereHas('exams')
                ->where('id', '!=', $user->id)
                ->withSum('exams', 'total_score')
                ->having('exams_sum_total_score', '>', $myTotalScore)
                ->count();

            $myRank = $usersAboveMe + 1;
        }

        // Format the leaderboard data
        $leaderboard = $topUsers->map(function ($u) use ($user) {
            return [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'phone'       => $u->phone,
                'state'       => $u->state,
                'total_score' => (int) ($u->exams_sum_total_score ?? 0),
                'is_current_user' => $u->id === $user->id,
            ];
        });

        return response()->json([
            'message'     => 'Top 10 leaderboard (including you if you qualify)',
            'my_rank'     => $myRank,           // null if user has no exams
            'my_total_score' => $myTotalScore,
            'data'        => $leaderboard,
        ]);
    }

    /**
     * Full paginated leaderboard - only for superadmins
     */
    public function index(Request $request)
    {
        $user = $request->user();

        abort_unless($user->hasRole('superadmin'), 403, 'Superadmin access only');

        $leaderboard = User::where('role', 'user')
            ->whereHas('exams')
            ->withSum('exams', 'total_score')
            ->orderByDesc('exams_sum_total_score')
            ->paginate(20);

        $leaderboard->getCollection()->transform(function ($user) {
            return [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'phone'       => $user->phone,
                'state'       => $user->state,
                'total_score' => (int) ($user->exams_sum_total_score ?? 0),
            ];
        });

        return response()->json([
            'message' => 'Full leaderboard of users who took exams',
            'data'    => $leaderboard,
        ]);
    }
}
