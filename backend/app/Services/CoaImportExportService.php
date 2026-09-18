<?php

namespace App\Services;

use App\Models\LedgerAccount;
use Illuminate\Support\Collection;

class CoaImportExportService
{
    public function exportToArray(): array
    {
        return LedgerAccount::query()
            ->orderBy('code')
            ->get(['code', 'name', 'type', 'description', 'parent_id', 'is_active'])
            ->toArray();
    }

    public function exportToCsv(string $delimiter = ","): string
    {
        $rows = $this->exportToArray();
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['code', 'name', 'type', 'description', 'parent_code', 'is_active'], $delimiter);
        $parentCodes = LedgerAccount::pluck('code', 'id');
        foreach ($rows as $row) {
            $parentCode = $row['parent_id'] ? ($parentCodes[$row['parent_id']] ?? null) : null;
            fputcsv($out, [
                $row['code'],
                $row['name'],
                $row['type'],
                $row['description'],
                $parentCode,
                $row['is_active'] ? 1 : 0,
            ], $delimiter);
        }
        rewind($out);
        return stream_get_contents($out) ?: '';
    }

    /**
     * Import from CSV content. Columns: code,name,type,description,parent_code,is_active
     * - Creates new accounts if code not found.
     * - Updates existing accounts (name/type/description/is_active); parent set by parent_code when resolvable.
     */
    public function importFromCsv(string $csv, string $delimiter = ","): Collection
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter) ?: [];
        $normalized = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $results = collect();
        $codeToId = LedgerAccount::pluck('id', 'code');

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $data = array_combine($normalized, $row);
            if (!$data || empty($data['code'])) {
                continue;
            }

            $parentId = null;
            if (!empty($data['parent_code'])) {
                $parentId = $codeToId[$data['parent_code']] ?? null;
            }

            $payload = [
                'name' => $data['name'] ?? $data['code'],
                'type' => $data['type'] ?? 'asset',
                'description' => $data['description'] ?? null,
                'parent_id' => $parentId,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            ];

            $account = LedgerAccount::query()->where('code', $data['code'])->first();
            if ($account) {
                $account->update($payload);
                $results->push(['code' => $data['code'], 'action' => 'updated', 'id' => $account->id]);
            } else {
                $new = LedgerAccount::create(array_merge(['code' => $data['code']], $payload));
                $codeToId[$new->code] = $new->id;
                $results->push(['code' => $new->code, 'action' => 'created', 'id' => $new->id]);
            }
        }

        fclose($handle);
        return $results;
    }
}
