<?php

use App\Models\Agent;
use App\Models\Courrier;
use App\Models\Direction;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

uses(RefreshDatabase::class);

/** Nombre de pages d'un PDF (via FPDI). */
function pdfPageCount(string $bytes): int
{
    $tmp = tempnam(sys_get_temp_dir(), 'tst').'.pdf';
    file_put_contents($tmp, $bytes);
    try {
        return (new Fpdi())->setSourceFile($tmp);
    } finally {
        @unlink($tmp);
    }
}

function ctxFicheVentilationPdf(): array
{
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $bc = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'courrier']);
    $eDoe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction']);

    $c = Courrier::create(['numero' => 'C-77', 'objet' => 'Convocation', 'expediteur' => 'MININT', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $bc->id]);
    $imp = Imputation::create(['courrier_id' => $c->id, 'niveau' => 'dg', 'mentions' => ['execution', 'urgent'], 'observations' => 'Traiter avant vendredi', 'signataire_nom' => 'Le DG', 'saisi_par' => $bc->id]);
    $imp->destinataires()->sync([$eDoe->id]);

    return compact('bc', 'c', 'imp');
}

it('génère le PDF de la fiche de ventilation (bureau courrier)', function () {
    ['bc' => $bc, 'imp' => $imp] = ctxFicheVentilationPdf();

    $res = $this->actingAs($bc)->get(route('imputations.fiche.pdf', $imp));

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
});

it('refuse le PDF à un utilisateur sans accès (403)', function () {
    ['imp' => $imp] = ctxFicheVentilationPdf();
    $agent = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);

    $this->actingAs($agent)->get(route('imputations.fiche.pdf', $imp))->assertForbidden();
});

it('fusionne la fiche avec le scan IMAGE du courrier (même document)', function () {
    Storage::fake('public');
    ['bc' => $bc, 'c' => $c, 'imp' => $imp] = ctxFicheVentilationPdf();

    // PNG 1x1 valide.
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    Storage::disk('public')->put('courriers-scans/scan.png', $png);
    $c->update(['scan_path' => 'courriers-scans/scan.png']);

    $res = $this->actingAs($bc)->get(route('imputations.fiche.pdf', $imp));

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
    // fiche (1 page) + scan (1 page) => >= 2 pages dans le même document.
    expect(pdfPageCount($res->getContent()))->toBeGreaterThanOrEqual(2);
});

it('fusionne la fiche avec le scan PDF du courrier (même document)', function () {
    Storage::fake('public');
    ['bc' => $bc, 'c' => $c, 'imp' => $imp] = ctxFicheVentilationPdf();

    $scanPdf = Pdf::loadHTML('<h1>COURRIER SCANNÉ</h1>')->output();
    Storage::disk('public')->put('courriers-scans/scan.pdf', $scanPdf);
    $c->update(['scan_path' => 'courriers-scans/scan.pdf']);

    $res = $this->actingAs($bc)->get(route('imputations.fiche.pdf', $imp));

    $res->assertOk();
    expect(pdfPageCount($res->getContent()))->toBeGreaterThanOrEqual(2);
});
