<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Services\PublicationSearch;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ExportController extends Controller
{
    public function export(Request $r, PublicationSearch $search)
    {
        $d = $r->validate(['format' => 'required|in:csv,xlsx,json,xml,bib,ris']);
        $q = $search->query($r);
        abort_if((clone $q)->count() > config('catalogue.export_limit'), 422, 'Reduce los resultados con los filtros antes de exportar.');
        $rows = $q->get();
        $format = $d['format'];
        $name = 'referencias-'.now()->format('Ymd').'.'.$format;
        $fields = ['id', 'titulo', 'anio', 'tipo', 'autores', 'revista', 'doi', 'enlace', 'resumen'];
        $records = $rows->map(fn ($p) => [$p->id, $p->title, $p->year, $p->type, implode('; ', array_column($p->authors, 'nombre')), $p->venue, $p->doi, $p->external_url, $p->abstract]);
        if ($format === 'json') {
            return response()->json($rows->map(fn ($p) => ['id' => $p->id, 'titulo' => $p->title, 'anio' => $p->year, 'tipo' => $p->type, 'autores' => $p->authors, 'revista' => $p->venue, 'doi' => $p->doi, 'enlace' => $p->external_url, 'resumen' => $p->abstract, 'csl' => $p->csl(), 'bibliografia' => $p->metadata['bibliografia'] ?? []]))->header('Content-Disposition', 'attachment; filename="'.$name.'"');
        }
        if ($format === 'xlsx') {
            $path = tempnam(storage_path('app/private'), 'export-');
            $writer = new Writer;
            $writer->openToFile($path);
            $writer->addRow(Row::fromValues($fields));
            foreach ($records as $record) {
                $writer->addRow(Row::fromValues(array_map($this->spreadsheetCell(...), $record)));
            }$writer->close();

            return response()->download($path, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
        }
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($records, $fields) {
                $f = fopen('php://output', 'wb');
                fwrite($f, "\xEF\xBB\xBF");
                fputcsv($f, $fields, ',', '"', '');
                foreach ($records as $record) {
                    fputcsv($f, array_map($this->spreadsheetCell(...), $record), ',', '"', '');
                }fclose($f);
            }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        if ($format === 'xml') {
            $xml = new \XMLWriter;
            $xml->openMemory();
            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElement('publicaciones');
            foreach ($records as $record) {
                $xml->startElement('publicacion');
                foreach (array_combine($fields, $record) as $k => $v) {
                    $xml->writeElement($k, preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $v));
                }$xml->endElement();
            }$xml->endElement();

            return response($xml->outputMemory(), 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$name.'"']);
        }
        $text = $rows->map(function ($p) use ($format) {
            if ($format === 'ris') {
                $lines = ['TY  - '.($p->type === 'conference-paper' ? 'CPAPER' : 'JOUR'), 'TI  - '.$this->line($p->title)];
                foreach ($p->authors as $a) {
                    $lines[] = 'AU  - '.$this->line($a['nombre']);
                }foreach (['PY' => $p->year, 'JO' => $p->venue, 'DO' => $p->doi, 'UR' => $p->external_url, 'AB' => $p->abstract] as $k => $v) {
                    if ($v) {
                        $lines[] = $k.'  - '.$this->line($v);
                    }
                }

return implode("\r\n", $lines)."\r\nER  -\r\n";
            }$escape = fn ($s) => strtr($this->line((string) $s), ['\\' => '\\textbackslash{}', '{' => '\\{', '}' => '\\}']);
            $fields = ['title' => $p->title, 'author' => implode(' and ', array_map(fn ($a) => '{'.$escape($a['nombre']).'}', $p->authors)), 'year' => $p->year, 'journal' => $p->venue, 'doi' => $p->doi, 'url' => $p->external_url];

            return '@'.($p->type === 'conference-paper' ? 'inproceedings' : 'article').'{itc'.$p->id.",\n".collect($fields)->filter()->map(fn ($v, $k) => '  '.$k.' = {'.($k === 'author' ? $v : $escape($v)).'}')->implode(",\n")."\n}\n";
        })->implode("\n");

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$name.'"']);
    }

    private function spreadsheetCell($v)
    {
        return is_string($v) && preg_match('/^\s*[=+@\-]/u', $v) ? "'".$v : $v;
    }

    private function line($v)
    {
        return preg_replace('/\R/u', ' ', (string) $v);
    }

    public function pdf(Request $r, Publication $publication)
    {
        abort_unless($publication->status === 'published', 404);

        return Pdf::loadView('exports.ficha', compact('publication'))->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans'])->setPaper('a4')->download('ficha-'.$publication->id.'.pdf');
    }
}
