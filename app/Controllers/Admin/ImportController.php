<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\Audit;
use App\Services\CsvImport;

final class ImportController extends AdminController
{
    public const COLUMNS = CsvImport::COLUMNS;

    public function form(Request $r): Response
    {
        return $this->admin('products/import', [
            'plan' => null, 'token' => null,
            'jobs' => Database::get()->all('SELECT j.*, u.name AS actor FROM import_jobs j LEFT JOIN users u ON u.id = j.actor_id ORDER BY j.id DESC LIMIT 10'),
        ], 'Import products');
    }

    public function template(Request $r): Response
    {
        return $this->csv('uravex-product-import-template.csv', self::COLUMNS, [
            ['UVX-TEE01', 'Heavyweight Crew Tee', 'heavyweight-crew-tee', 't-shirts', 'draft', '2490', '', 'cotton,essentials', 'UVX-TEE01-BLK-M', 'M', 'Onyx', '#1A1A1A', '', '20', '5', '<p>Description with basic HTML.</p>'],
            ['UVX-TEE01', 'Heavyweight Crew Tee', 'heavyweight-crew-tee', 't-shirts', 'draft', '2490', '', 'cotton,essentials', 'UVX-TEE01-BLK-L', 'L', 'Onyx', '#1A1A1A', '', '15', '5', ''],
        ]);
    }

    public function preview(Request $r): Response
    {
        $file = $r->file('csv');
        if (!$file || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            throw ValidationException::one('file', 'Choose a CSV file to upload.');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw ValidationException::one('file', 'CSV files must be smaller than 5 MB.');
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if ($ext !== 'csv' || !in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv'], true)) {
            throw ValidationException::one('file', 'Upload a .csv file (comma separated, UTF-8).');
        }
        $rows = CsvImport::parse($file['tmp_name']);
        $plan = CsvImport::plan($rows);

        // Keep the parsed rows server-side for the confirm step (never trust a re-posted plan).
        $token = bin2hex(random_bytes(16));
        $dir = STORAGE_PATH . '/cache/imports';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        file_put_contents($dir . '/' . $token . '.json', json_encode(['rows' => $rows, 'name' => mb_substr((string) $file['name'], 0, 120), 'user' => $this->actorId()]));
        Session::put('import_token', $token);

        return $this->admin('products/import', ['plan' => $plan, 'token' => $plan['errors'] ? null : $token, 'jobs' => [], 'filename' => $file['name']], 'Import preview');
    }

    public function commit(Request $r): Response
    {
        $token = $r->str('token');
        if (!preg_match('/^[a-f0-9]{32}$/', $token) || Session::get('import_token') !== $token) {
            throw ValidationException::one('file', 'This import preview expired. Upload the file again.');
        }
        $path = STORAGE_PATH . '/cache/imports/' . $token . '.json';
        $stored = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!$stored || (int) $stored['user'] !== $this->actorId()) {
            throw ValidationException::one('file', 'This import preview expired. Upload the file again.');
        }
        Session::forget('import_token');
        @unlink($path);

        // Re-validate against the current database state before writing.
        $plan = CsvImport::plan($stored['rows']);
        $db = Database::get();
        if ($plan['errors']) {
            $db->insert('INSERT INTO import_jobs (actor_id, filename, total_rows, failed, errors, status) VALUES (?, ?, ?, ?, ?, ?)',
                [$this->actorId(), $stored['name'], count($stored['rows']), count($plan['errors']), json_encode(array_slice($plan['errors'], 0, 200)), 'rejected']);
            return $this->admin('products/import', ['plan' => $plan, 'token' => null, 'jobs' => [], 'filename' => $stored['name']], 'Import preview');
        }
        $result = CsvImport::commit($plan, $this->actorId());
        $db->insert('INSERT INTO import_jobs (actor_id, filename, total_rows, created, updated, failed, errors, status) VALUES (?, ?, ?, ?, ?, 0, ?, ?)',
            [$this->actorId(), $stored['name'], count($stored['rows']), $result['created'], $result['updated'], '[]', 'completed']);
        Audit::log('product.import', 'import', null, null, $result + ['file' => $stored['name'], 'rows' => count($stored['rows'])]);
        return $this->redirect('/admin/products/import', "Import complete: {$result['created']} created, {$result['updated']} updated, {$plan['summary']['variants']} variants processed.");
    }
}
