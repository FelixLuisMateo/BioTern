<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Internship;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use PDF;

class DocumentService
{
    public function generateDocument(Internship $internship, $type)
    {
        $template = $this->getTemplate($type, $internship);
        
        $html = View::make("documents.templates.{$type}", [
            'internship' => $internship,
            'student' => $internship->student,
        ])->render();

        $fileName = $this->generateFileName($type, $internship);
        
        $path = $this->savePDF($html, $fileName);

        return Document::create([
            'internship_id' => $internship->id,
            'student_id' => $internship->student_id,
            'type' => $type,
            'file_name' => $fileName,
            'file_path' => $path,
            'file_mime_type' => 'application/pdf',
            'file_size' => Storage::size($path),
            'generated_by' => auth()->id(),
            'generated_at' => now(),
        ]);
    }

    private function getTemplate($type, Internship $internship)
    {
        return view("documents.templates.{$type}", [
            'internship' => $internship,
            'student' => $internship->student,
        ]);
    }

    private function savePDF($html, $fileName)
    {
        $pdf = PDF::loadHTML($html);
        $path = "documents/generated/{$fileName}.pdf";
        Storage::put($path, $pdf->output());
        return $path;
    }

    private function generateFileName($type, Internship $internship)
    {
        return "{$type}_{$internship->student_id}_" . now()->format('Y-m-d_His');
    }

    public function downloadDocument(Document $document)
    {
        $document->update(['downloaded_at' => now()]);
        return Storage::download($document->file_path, $document->file_name);
    }
}