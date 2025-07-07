<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index()
    {
        return view('document.index');
    }

    public function uploadDocument(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:txt,pdf|max:2048',
        ]);

        $file = $request->file('document');
        $fileName = $file->getClientOriginalName();
        $filePath = $file->storeAs('uploads', uniqid() . '_' . $fileName, 'public');

        Document::create([
            'file_name' => $fileName,
            'file_path' => $filePath,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Document uploaded successfully']);
    }

    public function listDocuments()
    {
        $documents = Document::all()->pluck('file_name', 'id');
        return response()->json(['status' => 'success', 'documents' => $documents]);
    }

    public function queryDocuments(Request $request)
    {
        $request->validate([
            'question' => 'required|string',
        ]);

        $userQuestion = trim(preg_replace('/[^ -~]/', '', $request->input('question')));
        $documents = Document::all();
        $context = '';

        foreach ($documents as $document) {
            $fullPath = storage_path('app/public/' . $document->file_path);
            if (file_exists($fullPath)) {
                if (pathinfo($fullPath, PATHINFO_EXTENSION) === 'txt') {
                    $content = file_get_contents($fullPath);
                    $context .= trim(preg_replace('/[^ -~]/', '', Str::limit($content, 1000))) . "\n\n";
                } else {
                    $parser = new Parser();
                    $pdf = $parser->parseFile($fullPath);
                    $text = $pdf->getText();
                    $context .= trim(preg_replace('/[^ -~]/', '', Str::limit($text, 1000))) . "\n\n";
                }
            }
        }

        $prompt = "User question: {$userQuestion}\n\nDocument context:\n{$context}\n\nRespond in 50-100 words, addressing the question directly and incorporating relevant document information.";

        $models = ['gemini'];
        $responses = [];

        foreach ($models as $model) {
            $startTime = microtime(true);

            if ($model === 'gemini') {
                $response = $this->callGemini($prompt);
            } 

            $endTime = microtime(true);
            $responseTime = round($endTime - $startTime, 2) . 's';

            $responses[$model] = [
                'response' => $response,
                'response_time' => $responseTime,
            ];
        }

        return response()->json([
            'status' => 'success',
            'responses' => $responses,
        ]);
    }

    public function summarizeDocument(Request $request)
    {
        $request->validate([
            'selected_document' => 'required|exists:documents,id',
            'summary_type' => 'nullable|string|in:short,detailed,key_points',
        ]);

        $document = Document::findOrFail($request->input('selected_document'));
        $fullPath = storage_path('app/public/' . $document->file_path);

        if (!file_exists($fullPath)) {
            return response()->json(['status' => 'error', 'message' => 'Document not found'], 404);
        }

        $parser = new Parser();
        $pdf = $parser->parseFile($fullPath);
        $text = $pdf->getText();
        $cleanText = trim(preg_replace('/[^ -~]/', '', Str::limit($text, 4000)));

        $summaryType = $request->input('summary_type', 'short');

        switch ($summaryType) {
            case 'detailed':
                $summaryPrompt = "Create a 150-200 word summary of the following document. Include: 1) the document’s purpose, 2) main arguments or findings, and 3) key conclusions or recommendations. Use clear, professional language:\n\n{$cleanText}";
                break;
            case 'key_points':
                $summaryPrompt = "List 3-5 key points from the following document in bullet points, using concise and clear language:\n\n{$cleanText}";
                break;
            case 'short':
            default:
                $summaryPrompt = "Summarize the following document in 100-150 words, capturing the main ideas, key points, and purpose in clear, concise language:\n\n{$cleanText}";
                break;
        }

        $summary = $this->callGemini($summaryPrompt);

        return response()->json([
            'status' => 'success',
            'summary' => $summary,
        ]);
    }

    

    protected function callGemini($prompt)
    {
        try {
            $apiKey = env('GEMINI_API_KEY');
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey;

            $payload = [
                'contents' => [[
                    'parts' => [['text' => $prompt]]
                ]]
            ];

            \Log::info('Sending Gemini API request:', $payload);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($url, $payload);

            $json = $response->json();
            \Log::info('Gemini API response:', $json);

            if (isset($json['error'])) {
                \Log::error('Gemini API error:', [$json['error']]);
                return 'API error: ' . $json['error']['message'];
            }

            return $json['candidates'][0]['content']['parts'][0]['text'] ?? 'No Gemini response.';
        } catch (\Exception $e) {
            \Log::error('Gemini API call failed: ' . $e->getMessage());
            return 'Gemini error: ' . $e->getMessage();
        }
    }
}