<?php

namespace App\Services\CBT;

use App\Models\Exam;
use Barryvdh\DomPDF\Facade\Pdf;

class ResultPdfService
{
    public function generate(Exam $exam, array $breakdown)
    {
        return Pdf::loadView('pdf.result-slip', [
            'exam' => $exam,
            'breakdown' => $breakdown,
        ])->download("result-slip-{$exam->id}.pdf");
    }
}
