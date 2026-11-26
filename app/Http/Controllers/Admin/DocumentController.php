<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const DISK = 'local'; // private: files are only reachable through the download route

    private const MIMES = 'pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png';

    /** Staff who manage documents see everything, including drafts, scheduled and expired ones. */
    private function manager(): bool
    {
        return (bool) auth()->user()?->can('documents.manage');
    }

    /** Lower-case role names of the signed-in user, from the user_roles table. */
    private function roles(): array
    {
        return DB::table('user_roles')->where('user_id', auth()->id())->pluck('role')
            ->map(fn ($r) => strtolower((string) $r))->all();
    }

    /** Everything this user is allowed to see. This is the single place audience rules are enforced. */
    private function visible()
    {
        $q = SchoolDocument::query();

        return $this->manager() ? $q : $q->forRoles($this->roles());
    }

    private function userName($u): string
    {
        return $u ? ($u->name ?? $u->username ?? ('User #'.$u->id)) : '—';
    }

    /** Filters shared by the list, tab counts and CSV. The tab is applied separately. */
    private function filtered(Request $request)
    {
        return $this->visible()
            ->with('uploader')
            ->when($request->filled('category') && isset(SchoolDocument::CATEGORIES[$request->category]),
                fn ($q) => $q->where('category', $request->category))
            ->when($this->manager() && $request->filled('audience') && isset(SchoolDocument::AUDIENCES[$request->audience]),
                fn ($q) => $q->where('audience', $request->audience))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(fn ($q) => $q->where('title', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%")
                    ->orWhere('file_name', 'like', "%{$s}%"));
            });
    }

    private function applyTab($query, string $tab)
    {
        return match ($tab) {
            'active' => $query->active(),
            'draft' => $query->where('is_published', false),
            'expiring' => $query->expiring(),
            'expired' => $query->expired(),
            default => $query,
        };
    }

    public function index(Request $request)
    {
        $manager = $this->manager();
        $tab = $manager && in_array($request->get('tab'), SchoolDocument::TABS, true) ? $request->get('tab') : 'all';
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;

        $documents = $this->applyTab($this->filtered($request), $tab)
            ->orderByRaw('coalesce(effective_date, date(created_at)) desc')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $counts = [];
        $stats = [];
        if ($manager) {
            foreach (SchoolDocument::TABS as $t) {
                $counts[$t] = $this->applyTab($this->filtered($request), $t)->count();
            }

            $stats = [
                'total' => SchoolDocument::count(),
                'active' => SchoolDocument::active()->count(),
                'draft' => SchoolDocument::where('is_published', false)->count(),
                'expiring' => SchoolDocument::expiring()->count(),
                'downloads' => (int) SchoolDocument::sum('download_count'),
            ];
        }

        return response()->json([
            'documents' => $documents,
            'counts' => $counts,
            'stats' => $stats,
            'tab' => $tab,
            'perPage' => $perPage,
            'isManager' => $manager,
        ]);
    }

    private function rules(Request $request): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', Rule::in(array_keys(SchoolDocument::CATEGORIES))],
            'audience' => ['required', Rule::in(array_keys(SchoolDocument::AUDIENCES))],
            'effective_date' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', $request->filled('effective_date') ? 'after_or_equal:effective_date' : 'date'],
            'external_url' => ['nullable', 'url', 'max:500'],
            'is_published' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:'.self::MIMES],
        ];
    }

    private function attributes(Request $request, array $data): array
    {
        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'],
            'audience' => $data['audience'],
            'effective_date' => $data['effective_date'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'external_url' => $data['external_url'] ?? null,
            'is_published' => $request->boolean('is_published'),
        ];
    }

    private function fileAttributes($file): array
    {
        return [
            'file_size' => $file->getSize(),
            'mime' => $file->getClientMimeType(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $file->store('documents', self::DISK),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request));

        if (! $request->hasFile('file') && blank($data['external_url'] ?? null)) {
            return response()->json(["message" => "Validation failed."], 422);
        }

        $attrs = $this->attributes($request, $data) + ['uploaded_by' => auth()->id(), 'version' => 1];

        if ($file = $request->file('file')) {
            $attrs += $this->fileAttributes($file);
        }

        $doc = SchoolDocument::create($attrs);

        return response()->json(["message" => '"'.$doc->title.'" '.($doc->is_published ? 'is now available to '.strtolower(SchoolDocument::AUDIENCES[$doc->audience]).'.' : 'was saved as a draft.')]);
    }

    public function update(Request $request, SchoolDocument $document)
    {
        $data = $request->validate($this->rules($request));
        $attrs = $this->attributes($request, $data);
        $file = $request->file('file');

        if (! $file && ! $document->file_path && blank($data['external_url'] ?? null)) {
            return response()->json(["message" => "Validation failed."], 422);
        }

        $newVersion = false;
        $oldPath = $document->file_path;
        $newPath = null;
        if ($file) {
            // Replacing the file creates a new version. The replacement is
            // stored first and the old file is only deleted after the update
            // succeeds, so a failed store never destroys the existing file.
            $newPath = $this->fileAttributes($file)['file_path'];
            $attrs['file_path'] = $newPath;
            $attrs['version'] = $document->version + 1;
            $newVersion = true;
        }

        // Persist metadata first. If this fails (returns false) or throws,
        // clean up the newly stored file so the previous file and the
        // previous database path remain intact.
        try {
            $metadataSaved = $document->update($attrs);
        } catch (\Throwable $e) {
            if ($newPath && Storage::disk(self::DISK)->exists($newPath)) {
                Storage::disk(self::DISK)->delete($newPath);
            }

            throw $e;
        }

        if ($newVersion) {
            if ($metadataSaved && $oldPath && $document->file_path !== $oldPath) {
                // Update succeeded: delete the previous file now that the
                // new one is safely on disk and the database reflects it.
                Storage::disk(self::DISK)->delete($oldPath);

                if (Storage::disk(self::DISK)->exists($oldPath)) {
                    // A filesystem is not part of the database transaction:
                    // never roll back the persisted update, but make the
                    // cleanup failure visible so the leftover file can be
                    // handled safely by an administrator.
                    Log::warning('Document replacement succeeded but deleting the previous file failed.', [
                        'document' => $document->id,
                        'leftover_file' => $oldPath,
                    ]);

                    return response()->json(["message" => "\"{$document->title}\" updated to version {$document->version}."]);
                }
            } elseif (!$metadataSaved) {
                // Update failed: remove the newly stored file so the previous
                // file remains intact, and do not change the database state.
                if ($newPath && Storage::disk(self::DISK)->exists($newPath)) {
                    Storage::disk(self::DISK)->delete($newPath);
                }
            }
        }

        return response()->json(["message" => $newVersion ? "\"{$document->title}\" updated to version {$document->version}." : 'Document updated.']);
    }

    /** Switch between published and draft without opening the edit form. */
    public function publish(SchoolDocument $document)
    {
        $document->update(['is_published' => ! $document->is_published]);

        return response()->json(["message" => $document->is_published ? 'Document published.' : 'Document moved to drafts.']);
    }

    public function destroy(SchoolDocument $document)
    {
        $document->delete(); // soft delete: the file stays in storage so the document can be restored

        return response()->json(["message" => 'Document deleted.']);
    }

    /** Download, preview or open the link. The audience check happens here, on every request. */
    public function download(Request $request, SchoolDocument $document)
    {
        abort_unless($this->visible()->whereKey($document->id)->exists(), 403);

        // Count without touching updated_at
        DB::table('school_documents')->where('id', $document->id)->update([
            'download_count' => DB::raw('download_count + 1'),
            'last_downloaded_at' => now(),
        ]);

        if (! $document->file_path && $document->external_url) {
            return response()->json(["ok" => true]);
        }

        if (! $document->file_path || ! Storage::disk(self::DISK)->exists($document->file_path)) {
            return response()->json(["message" => "Validation failed."], 422);
        }

        if ($request->boolean('view') && $document->isPreviewable()) {
            return response()->file(Storage::disk(self::DISK)->path($document->file_path), [
                'Content-Type' => $document->mime,
                'Content-Disposition' => 'inline; filename="'.$document->downloadName().'"',
            ]);
        }

        return Storage::disk(self::DISK)->download($document->file_path, $document->downloadName());
    }

    public function csv(Request $request): StreamedResponse
    {
        $rows = $this->applyTab($this->filtered($request), $this->manager() ? $request->get('tab', 'all') : 'all')
            ->orderByRaw('coalesce(effective_date, date(created_at)) desc')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Nepali text correctly

            fputcsv($out, ['Title', 'Category', 'Audience', 'Effective', 'Expires', 'Version', 'Status', 'File',
                'Link', 'Downloads', 'Uploaded By', 'Added On']);

            foreach ($rows as $d) {
                fputcsv($out, [
                    $d->title,
                    SchoolDocument::CATEGORIES[$d->category] ?? $d->category,
                    SchoolDocument::AUDIENCES[$d->audience] ?? $d->audience,
                    $d->effective_date?->format('Y-m-d'),
                    $d->expires_on?->format('Y-m-d'),
                    $d->version,
                    $d->statusKey(),
                    $d->file_name,
                    $d->external_url,
                    $d->download_count,
                    $this->userName($d->uploader),
                    $d->created_at->format('Y-m-d'),
                ]);
            }

            fclose($out);
        }, 'documents-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
