<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentActivity;
use App\Models\DocumentRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $userId = $user->id;

        // --- Summary cards ---
        $totalDocuments = Document::where('user_id', $userId)->count();
        $totalRecipients = DocumentRecipient::whereHas('document', fn ($q) => $q->where('user_id', $userId))->count();

        $statusCounts = Document::where('user_id', $userId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $recipientStatusCounts = DocumentRecipient::whereHas('document', fn ($q) => $q->where('user_id', $userId))
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // --- Monthly document trend (last 6 months, computed in PHP for DB portability) ---
        $sixMonthsAgo = now()->subMonths(6)->startOfMonth();
        $recentDocs = Document::where('user_id', $userId)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->select('status', 'created_at')
            ->get();

        $monthlyMap = [];
        foreach ($recentDocs as $doc) {
            $month = $doc->created_at->format('Y-m');
            if (!isset($monthlyMap[$month])) {
                $monthlyMap[$month] = ['month' => $month, 'total' => 0, 'completed' => 0, 'drafts' => 0];
            }
            $monthlyMap[$month]['total']++;
            if ($doc->status === 'completed') {
                $monthlyMap[$month]['completed']++;
            }
            if ($doc->status === 'draft') {
                $monthlyMap[$month]['drafts']++;
            }
        }
        ksort($monthlyMap);
        $monthlyDocuments = array_values($monthlyMap);

        // --- Completion rate ---
        $nonDraftCount = Document::where('user_id', $userId)->where('status', '!=', 'draft')->count();
        $completedCount = $statusCounts['completed'] ?? 0;
        $completionRate = $nonDraftCount > 0 ? round(($completedCount / $nonDraftCount) * 100, 1) : 0;

        // --- Average time to complete (computed in PHP for DB portability) ---
        $completedDocs = Document::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->select('created_at', 'completed_at')
            ->get();

        $avgCompletionHours = null;
        if ($completedDocs->isNotEmpty()) {
            $totalHours = $completedDocs->sum(fn ($d) => $d->created_at->diffInMinutes($d->completed_at) / 60);
            $avgCompletionHours = round($totalHours / $completedDocs->count(), 1);
        }

        // --- Recent activity ---
        $recentActivity = DocumentActivity::whereHas('document', fn ($q) => $q->where('user_id', $userId))
            ->with(['document:id,title', 'recipient:id,name,email'])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'description' => $a->description,
                'document_title' => $a->document?->title,
                'document_id' => $a->document_id,
                'recipient_name' => $a->recipient?->name ?: $a->recipient?->email,
                'created_at' => $a->created_at->toIso8601String(),
            ])
            ->toArray();

        // --- Recent documents ---
        $recentDocuments = Document::where('user_id', $userId)
            ->with('recipients:id,document_id,name,email,status,signed_at')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'status' => $d->status,
                'created_at' => $d->created_at->toIso8601String(),
                'completed_at' => $d->completed_at?->toIso8601String(),
                'recipients' => $d->recipients->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name ?: $r->email,
                    'status' => $r->status,
                ])->toArray(),
            ])
            ->toArray();

        // --- Documents awaiting action ---
        $awaitingAction = Document::where('user_id', $userId)
            ->whereIn('status', ['sent', 'in_progress'])
            ->count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalDocuments' => $totalDocuments,
                'totalRecipients' => $totalRecipients,
                'completedCount' => $completedCount,
                'awaitingAction' => $awaitingAction,
                'completionRate' => $completionRate,
                'avgCompletionHours' => $avgCompletionHours,
                'statusCounts' => $statusCounts,
                'recipientStatusCounts' => $recipientStatusCounts,
                'monthlyDocuments' => $monthlyDocuments,
                'recentActivity' => $recentActivity,
                'recentDocuments' => $recentDocuments,
            ],
        ]);
    }
}
