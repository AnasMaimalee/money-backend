<?php

namespace App\Services\CBT\SuperAdmin;

use App\Repositories\CBT\SuperAdmin\QuestionUploadRepository;
use Illuminate\Support\Str;

class QuestionUploadService
{
    public function __construct(
        protected QuestionUploadRepository $repository
    ) {}

    public function upload($file): array
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'rb');

        if (!$handle) {
            return ['uploaded' => 0, 'failed' => 1, 'errors' => ['Could not open file']];
        }

        // Skip BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        fgetcsv($handle); // Skip header

        $count = 0;
        $errors = [];
        $lineNum = 2;

        while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
            $lineNum++;

            // Skip empty rows
            if (empty(array_filter($row))) continue;

            // **CRITICAL FIX: Remove outer quotes from each field**
            $row = array_map(function($field) {
                return trim($field, '"\' ');
            }, $row);

            // **HANDLE THE 1-COLUMN PROBLEM - Manual split if needed**
            if (count($row) === 1 && strpos($row[0], ',') !== false) {
                $row = str_getcsv($row[0], ','); // Force re-parse
                $row = array_map(function($field) {
                    return trim($field, '"\' ');
                }, $row);
            }

            // Pad if too short
            while (count($row) < 9) $row[] = '';

            $data = [
                'subject_id' => $row[0],
                'question' => $row[1],
                'option_a' => $row[2] ?: 'A',
                'option_b' => $row[3] ?: 'B',
                'option_c' => $row[4] ?: 'C',
                'option_d' => $row[5] ?: 'D',
                'correct_option' => strtoupper($row[6]),
                'image' => $row[7] ?? '',
                'year' => $row[8] ?? '',
            ];

            // Basic validation
            if (strlen($data['subject_id']) > 20 &&
                strlen($data['question']) > 0 &&
                in_array($data['correct_option'], ['A','B','C','D'])) {

                try {
                    $this->repository->create([
                        'id' => Str::uuid()->toString(),
                        'subject_id' => $data['subject_id'],
                        'question' => $data['question'],
                        'option_a' => $data['option_a'],
                        'option_b' => $data['option_b'],
                        'option_c' => $data['option_c'],
                        'option_d' => $data['option_d'],
                        'correct_option' => $data['correct_option'],
                        'image' => $data['image'],
                        'year' => $data['year'],
                    ]);
                    $count++;
                } catch (\Exception $e) {
                    $errors[] = "Line $lineNum: " . $e->getMessage();
                }
            }
        }

        fclose($handle);
        return ['uploaded' => $count, 'failed' => count($errors), 'errors' => $errors];
    }

}
