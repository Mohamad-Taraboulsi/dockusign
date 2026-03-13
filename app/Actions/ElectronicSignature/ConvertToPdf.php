<?php

namespace App\Actions\ElectronicSignature;

use Illuminate\Support\Facades\Process;

class ConvertToPdf
{
    public function execute(string $inputPath, string $outputDir): ?string
    {
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $result = Process::run([
            'soffice',
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $outputDir,
            $inputPath,
        ]);

        if (! $result->successful()) {
            return null;
        }

        $baseName = pathinfo($inputPath, PATHINFO_FILENAME);
        $pdfPath = $outputDir.'/'.$baseName.'.pdf';

        return file_exists($pdfPath) ? $pdfPath : null;
    }

    public function getPageCount(string $pdfPath): int
    {
        if (! file_exists($pdfPath)) {
            return 0;
        }

        $content = file_get_contents($pdfPath);

        return $this->getPageCountFromContent($content);
    }

    public function getPageCountFromContent(string $content): int
    {
        // Count pages by looking for /Type /Page (not /Pages)
        preg_match_all('/\/Type\s*\/Page[^s]/', $content, $matches);

        return max(count($matches[0]), 1);
    }
}
