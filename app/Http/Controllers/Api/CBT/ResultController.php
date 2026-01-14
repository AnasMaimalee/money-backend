<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\CBT\ResultService;
use App\Services\CBT\ResultPdfService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ResultController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ResultService $resultService,
        protected ResultPdfService $pdfService
    ) {}

    /**
     * Show full result with breakdown
     */
    public function show(Exam $exam)
    {
        $this->authorize('view', $exam);

        $result = $this->resultService->getResult($exam);

        return response()->json([
            'message' => 'Result fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Summary for dashboard / history
     */
    public function summary(Exam $exam)
    {
        $this->authorize('view', $exam);

        $summary = $this->resultService->summary($exam);

        return response()->json([
            'message' => 'Summary fetched successfully',
            'data' => $summary,
        ]);
    }

    /**
     * Generate PDF result slip
     */
    public function downloadPdf(Exam $exam)
    {
        $this->authorize('downloadPdf', $exam);

        $pdf = $this->pdfService->generate($exam);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "Exam_Result_{$exam->id}.pdf"
        );
    }
}
