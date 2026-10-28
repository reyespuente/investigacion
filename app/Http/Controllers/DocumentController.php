<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Publication;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function store(Request $r)
    {
        $profile = $r->user()->researcher;
        abort_unless($profile, 403, 'Primero reclama tu perfil.');
        $d = $r->validate(['title' => 'required|string|max:500', 'publication_id' => 'nullable|integer|exists:publications,id', 'kind' => 'required|in:publication,certification,degree,other', 'document' => 'required|file|mimes:pdf|max:20480', 'license' => 'required|in:author_permission,cc_by,cc_by_sa,open_license,private', 'rights' => 'accepted', 'is_public' => 'nullable|boolean']);
        if (! empty($d['publication_id'])) {
            Gate::authorize('update', Publication::findOrFail($d['publication_id']));
        }
        abort_unless(str_starts_with(file_get_contents($r->file('document')->getRealPath(), false, null, 0, 5), '%PDF-'), 422, 'El archivo no es un PDF válido.');
        $disk = config('filesystems.default');
        $path = $r->file('document')->store('repository/documents', $disk);
        abort_unless($path, 503, 'No se pudo guardar el documento.');
        try {
            $doc = Document::create(['researcher_id' => $profile->id, 'publication_id' => $d['publication_id'] ?? null, 'uploaded_by' => $r->user()->id, 'title' => $d['title'], 'kind' => $d['kind'], 'path' => $path, 'disk' => $disk, 'size' => $r->file('document')->getSize(), 'license' => $d['license'], 'is_public' => $r->boolean('is_public') && $d['license'] !== 'private']);
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }Audit::record('document', $doc->id, 'uploaded', null, $doc->toArray());

        return back()->with('success', 'Documento guardado. Solo se mostrará públicamente si autorizaste su publicación.');
    }

    public function show(Request $r, Document $document)
    {
        abort_unless($document->visibleTo($r->user()), 404);

        return view('documents.show', compact('document'));
    }

    public function stream(Request $r, Document $document)
    {
        abort_unless($document->visibleTo($r->user()), 404);

        return Storage::disk($document->disk)->response($document->path, 'documento-'.$document->id.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, max-age=0', 'Content-Disposition' => 'inline; filename="documento-'.$document->id.'.pdf"']);
    }

    public function hide(Request $r, Document $document)
    {
        abort_unless($r->user()->role === 'admin' || $document->researcher->user_id === $r->user()->id, 403);
        $document->update(['is_public' => false]);
        Audit::record('document',$document->id,'unpublished');

        return back()->with('success','El documento dejó de ser público.');
    }
}
