<?php

declare(strict_types=1);

namespace App\Http;

use App\Database\Connection;
use App\Service\CsvParser;
use App\Service\UserImporter;
use App\Service\UserValidator;
use Throwable;

/**
 * Handles the two REST endpoints used by the React UI:
 *
 *   POST /api/upload  — upload a CSV and get a validated preview
 *   POST /api/import  — import the supplied (already-validated) data
 */
final class ApiController
{
    public function handle(): void
    {
        // Allow requests from the Vite dev server (localhost:5173)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Content-Type: application/json; charset=UTF-8');

        // Preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return;
        }

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = rtrim($path, '/');

        try {
            match ($path) {
                '/api/upload' => $this->handleUpload(),
                '/api/import' => $this->handleImport(),
                default       => $this->notFound($path),
            };
        } catch (Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    // ─── POST /api/upload ────────────────────────────────────────────────────

    private function handleUpload(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Method not allowed', 405);
            return;
        }

        if (!isset($_FILES['file'])) {
            $this->jsonError('No file uploaded. Send a multipart/form-data request with field "file".', 400);
            return;
        }

        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->jsonError('File upload error: ' . $this->uploadErrorMessage($file['error']), 400);
            return;
        }

        // Validate MIME type loosely — allow text/csv and text/plain
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'], true)) {
            // Also allow based on extension
            if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
                $this->jsonError("Unsupported file type: {$mime}. Please upload a .csv file.", 400);
                return;
            }
        }

        $csvContent = file_get_contents($file['tmp_name']);

        if ($csvContent === false) {
            $this->jsonError('Could not read uploaded file.', 500);
            return;
        }

        $strict    = !empty($_POST['strict']);
        $parser    = new CsvParser($strict);
        $validator = new UserValidator();

        try {
            $rows      = $parser->parseString($csvContent);
            $validated = $validator->validate($rows);
        } catch (Throwable $e) {
            $this->jsonError($e->getMessage(), 422);
            return;
        }

        $valid   = array_values(array_filter($validated, fn($u) => $u->isValid()));
        $invalid = array_values(array_filter($validated, fn($u) => !$u->isValid()));

        echo json_encode([
            'total'   => count($validated),
            'valid'   => count($valid),
            'invalid' => count($invalid),
            'users'   => array_map(fn($u) => $u->toArray(), $validated),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    // ─── POST /api/import ────────────────────────────────────────────────────

    private function handleImport(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Method not allowed', 405);
            return;
        }

        $body = file_get_contents('php://input');

        if (!$body) {
            $this->jsonError('Empty request body.', 400);
            return;
        }

        $data = json_decode($body, true);

        if (!isset($data['users']) || !is_array($data['users'])) {
            $this->jsonError('Request body must contain a "users" array.', 400);
            return;
        }

        // Re-validate the submitted users (defence-in-depth: never trust the client)
        $validator = new UserValidator();
        $rows = array_map(
            fn(array $u): array => [
                'name'    => $u['name']    ?? '',
                'surname' => $u['surname'] ?? '',
                'email'   => $u['email']   ?? '',
                '_line'   => $u['line']    ?? 0,
            ],
            $data['users']
        );

        $validated = $validator->validate($rows);

        $pdo      = Connection::get();
        $importer = new UserImporter($pdo);
        $summary  = $importer->import($validated);

        echo json_encode([
            'inserted' => $summary['inserted'],
            'skipped'  => $summary['skipped'],
            'errors'   => $summary['errors'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function notFound(string $path): void
    {
        $this->jsonError("Route not found: {$path}", 404);
    }

    private function jsonError(string $message, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode(['error' => $message], JSON_THROW_ON_ERROR);
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum allowed size.',
            UPLOAD_ERR_PARTIAL                        => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE                        => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR                     => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE                     => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION                      => 'Upload stopped by extension.',
            default                                   => "Unknown error (code {$code}).",
        };
    }
}
