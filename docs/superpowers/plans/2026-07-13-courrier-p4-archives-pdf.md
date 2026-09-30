# Courrier — Plan 4 : Archives & PDF Fiche de Ventilation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Donner à l'archiviste (Bureau Documentation & Archives) une consultation/recherche globale sur tout le courrier, et générer le **PDF de la Fiche de Ventilation** d'une imputation (reproduction du document papier : destinataires cochés + mentions « Soit transmis » + observations + signataire).

**Architecture:** Un écran `Courrier\Archives` (`role:archiviste`) réutilisant la fiche `CourrierEntite` pour le détail (la `CourrierPolicy::voir` autorise déjà l'archiviste). Un contrôleur `FicheVentilationPdfController` (barryvdh/laravel-dompdf, déjà installé) rendant une vue Blade imprimable pour une `Imputation`, autorisé via `Gate::authorize('voir', $imputation->courrier)`.

**Tech Stack:** Laravel 12, Livewire 3, barryvdh/laravel-dompdf, Pest, Tailwind. S'appuie sur P1 (`Entite`, rôle `archiviste`), P2 (`Courrier`, `Imputation`, MENTIONS), P3 (`CourrierPolicy::voir`, `CourrierEntite`).

**Prérequis d'exécution:** créer la branche `feat/courrier-p4` **à partir de** `feat/plan2-auth` :
```bash
cd /Users/admin/dge-rh-platform
git checkout feat/plan2-auth
git checkout -b feat/courrier-p4
```
Env local : PHP 8.5.5, MySQL 9.6 (root sans mot de passe), base `dge_rh`. `php` émet un `Warning: Module "swoole" is already loaded` inoffensif — l'ignorer. Tests sur SQLite in-memory ; tests HTTP rendant un layout `@vite` → `$this->withoutVite();`. Toujours `cd /Users/admin/dge-rh-platform` en tête de chaque commande.

**GOTCHA Livewire 3.8.2 (rappel P3) :** `Livewire::test()` intercepte `AuthorizationException`/`HttpException` et rend un 403 au lieu de re-lancer → tester une policy avec `->assertForbidden()`, jamais `->toThrow()`. Pour un contrôleur HTTP classique (le PDF), `$this->get(...)->assertForbidden()` fonctionne normalement.

**Référence spec:** `docs/superpowers/specs/2026-07-13-module-courrier-design.md` §7 (accès archiviste : voit tout), §9 (archivage/consultation), §12 (PDF Fiche de Ventilation).

**Faits vérifiés dans le code (ne pas re-supposer) :**
- `User::isArchiviste()` / `isCourrier()` existent (app/Models/User.php). `CourrierPolicy::voir` autorise courrier+archiviste sur tout (app/Policies/CourrierPolicy.php).
- `Imputation` : `MENTIONS` (12), `mentions` cast array, relations `courrier()`, `entiteSource()`, `destinataires()` (belongsToMany `entite_imputation`), `saisiPar()`. `niveau` ∈ {dg, direction}.
- `Courrier` : `numero/objet/expediteur/date_arrivee/date_depart`, `imputations()` (latest), `scanUrl()` relative, scope `pourEntites()`.
- `Entite` : `code/nom/type/parent_id/chef_agent_id/secretaire_agent_id/actif`, relations `parent()`, `chef()`, `secretaire()`. Types : direction/service/division.
- `Agent::nomComplet()` existe. `config('dge.dg_nom')` = 'Le Directeur Général', `config('dge.dg_fonction')` = 'Directeur Général des Élections'.
- Pattern PDF existant : `App\Http\Controllers\OrdreMissionPdfController` (`Barryvdh\DomPDF\Facade\Pdf::loadView(...)->stream(...)`).
- `CourrierEntite` (P3) a un lien retour `route('mes-courriers')` — inaccessible à l'archiviste ; ce plan le rend dynamique.

---

## Fichiers créés/modifiés dans ce plan

- `app/Livewire/Courrier/Archives.php` + `resources/views/livewire/courrier/archives.blade.php`.
- `app/Livewire/Courrier/CourrierEntite.php` (retour dynamique + lien PDF, modifié) + sa vue.
- `app/Http/Controllers/FicheVentilationPdfController.php` + `resources/views/pdf/fiche-ventilation.blade.php`.
- `routes/web.php` + `resources/views/components/layouts/rh.blade.php` (nav, modifiés).
- `tests/Feature/Courrier/{Archives,FicheVentilationPdf}Test.php`.

---

## Task 1: Archives — consultation globale (archiviste)

**Files:**
- Create: `app/Livewire/Courrier/Archives.php`, `resources/views/livewire/courrier/archives.blade.php`
- Modify: `app/Livewire/Courrier/CourrierEntite.php`, `resources/views/livewire/courrier/courrier-entite.blade.php`, `routes/web.php`, `resources/views/components/layouts/rh.blade.php`
- Test: `tests/Feature/Courrier/ArchivesTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Courrier/ArchivesTest.php`:
```php
<?php

use App\Livewire\Courrier\Archives;
use App\Livewire\Courrier\CourrierEntite;
use App\Models\Courrier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxArchives(): array
{
    $bc = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    $arch = User::create(['name' => 'Arch', 'matricule' => 'AR1', 'password' => bcrypt('s'), 'role' => 'archiviste']);
    $c1 = Courrier::create(['numero' => 'C-001', 'objet' => 'Budget 2026', 'expediteur' => 'Ministère', 'date_arrivee' => '2026-07-01', 'enregistre_par' => $bc->id]);
    $c2 = Courrier::create(['numero' => 'C-002', 'objet' => 'Recensement', 'expediteur' => 'Préfecture', 'date_arrivee' => '2026-07-05', 'enregistre_par' => $bc->id]);

    return compact('arch', 'c1', 'c2');
}

it('interdit les archives aux non-archivistes (403)', function () {
    $this->withoutVite();
    $bc = User::create(['name' => 'BC', 'matricule' => 'BC9', 'password' => bcrypt('s'), 'role' => 'courrier']);

    $this->actingAs($bc)->get('/archives')->assertForbidden();
});

it('l’archiviste voit tous les courriers', function () {
    ['arch' => $arch] = ctxArchives();

    Livewire::actingAs($arch)->test(Archives::class)
        ->assertSee('C-001')->assertSee('C-002');
});

it('la recherche filtre par numéro / objet / expéditeur', function () {
    ['arch' => $arch] = ctxArchives();

    Livewire::actingAs($arch)->test(Archives::class)
        ->set('search', 'Recensement')
        ->assertSee('C-002')->assertDontSee('C-001');
});

it('l’archiviste peut ouvrir la fiche de n’importe quel courrier', function () {
    ['arch' => $arch, 'c1' => $c1] = ctxArchives();

    Livewire::actingAs($arch)->test(CourrierEntite::class, ['courrier' => $c1])->assertOk();
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/ArchivesTest.php`
Expected: FAIL (composant/route absents).

- [ ] **Step 3: Composant Archives**

Create `app/Livewire/Courrier/Archives.php`:
```php
<?php

namespace App\Livewire\Courrier;

use App\Models\Courrier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.rh')]
class Archives extends Component
{
    use WithPagination;

    public string $search = '';
    public string $du = '';
    public string $au = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDu(): void
    {
        $this->resetPage();
    }

    public function updatingAu(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $courriers = Courrier::query()
            ->withCount('imputations')
            ->when($this->search !== '', function ($q) {
                $t = '%'.$this->search.'%';
                $q->where(fn ($s) => $s->where('numero', 'like', $t)->orWhere('objet', 'like', $t)->orWhere('expediteur', 'like', $t));
            })
            ->when($this->du !== '', fn ($q) => $q->whereDate('date_arrivee', '>=', $this->du))
            ->when($this->au !== '', fn ($q) => $q->whereDate('date_arrivee', '<=', $this->au))
            ->latest('date_arrivee')->latest('id')
            ->paginate(20);

        return view('livewire.courrier.archives', ['courriers' => $courriers]);
    }
}
```

- [ ] **Step 4: Vue Archives**

Create `resources/views/livewire/courrier/archives.blade.php`:
```blade
<div>
    <h1 style="font-size:28px;font-weight:600;margin:0 0 4px">Archives courrier</h1>
    <p style="color:var(--muted);font-size:14px;margin:0 0 18px">Consultation de l'ensemble du registre</p>

    <div class="card" style="padding:14px;margin-bottom:18px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:1;min-width:220px;position:relative">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:17px;height:17px;position:absolute;left:12px;top:11px;color:var(--muted)"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher n° / objet / expéditeur…" class="field" style="padding-left:36px">
        </div>
        <div><label style="display:block;font-size:11px;color:var(--muted);margin-bottom:3px">Du</label><input type="date" wire:model.live="du" class="field"></div>
        <div><label style="display:block;font-size:11px;color:var(--muted);margin-bottom:3px">Au</label><input type="date" wire:model.live="au" class="field"></div>
    </div>

    <div class="card" style="overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
                <tr style="background:var(--surface-2);color:var(--muted);text-align:left;font-size:12px;letter-spacing:.04em;text-transform:uppercase">
                    <th style="padding:12px 18px;font-weight:600">N°</th><th style="padding:12px 12px;font-weight:600">Objet</th>
                    <th style="padding:12px 12px;font-weight:600">Expéditeur</th><th style="padding:12px 12px;font-weight:600">Arrivée</th>
                    <th style="padding:12px 12px;font-weight:600">Imput.</th><th style="padding:12px 18px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($courriers as $c)
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px;font-weight:600">{{ $c->numero }}</td>
                        <td style="padding:11px 12px">{{ $c->objet }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ $c->expediteur }}</td>
                        <td style="padding:11px 12px">{{ $c->date_arrivee->format('d/m/Y') }}</td>
                        <td style="padding:11px 12px">{{ $c->imputations_count }}</td>
                        <td style="padding:11px 18px;text-align:right"><a href="{{ route('archives.fiche', $c) }}" class="btn btn-ghost" style="padding:6px 12px;font-size:13px;text-decoration:none">Ouvrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--muted)">Aucun courrier.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:18px">{{ $courriers->links() }}</div>
</div>
```

- [ ] **Step 5: Retour dynamique dans `CourrierEntite`**

Le lien retour de la fiche doit pointer vers `archives` pour l'archiviste, sinon `mes-courriers`. Modify `app/Livewire/Courrier/CourrierEntite.php` — dans `render()`, ajouter au tableau passé à la vue une clé `retour` :
```php
            'retour' => auth()->user()->isArchiviste() ? 'archives' : 'mes-courriers',
```
(Ajouter cette ligne parmi les données existantes retournées par `view('livewire.courrier.courrier-entite', [...])`.)

Modify `resources/views/livewire/courrier/courrier-entite.blade.php` — remplacer le lien retour codé en dur :
```blade
    <a href="{{ route('mes-courriers') }}" style="color:var(--muted);font-size:13px;text-decoration:none">← Mes courriers</a>
```
par :
```blade
    <a href="{{ route($retour) }}" style="color:var(--muted);font-size:13px;text-decoration:none">← {{ $retour === 'archives' ? 'Archives' : 'Mes courriers' }}</a>
```

- [ ] **Step 6: Routes + nav**

Modify `routes/web.php` — ajouter un groupe :
```php
Route::middleware(['auth', 'role:archiviste'])->group(function () {
    Route::get('/archives', \App\Livewire\Courrier\Archives::class)->name('archives');
    Route::get('/archives/{courrier}', \App\Livewire\Courrier\CourrierEntite::class)->name('archives.fiche');
});
```
Modify `resources/views/components/layouts/rh.blade.php` — dans la `<nav>`, après le bloc `@if ($__u->isCourrier())`, ajouter :
```blade
                @if ($__u->isArchiviste())
                    <a href="{{ route('archives') }}" class="{{ request()->routeIs('archives*') ? 'on' : '' }}">{!! $ico['bank'] !!} Archives courrier</a>
                @endif
```

- [ ] **Step 7: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/ArchivesTest.php`
Expected: PASS (4 tests). Re-run `tests/Feature/Courrier/ConsultationTest.php` (non-régression du retour dynamique).

- [ ] **Step 8: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat(courrier): archives consultation globale (archiviste)"
```

---

## Task 2: PDF Fiche de Ventilation

Génère le PDF d'une `Imputation` reproduisant la fiche papier : entête DGE, infos courrier, cases destinataires (cochées), grille des 12 mentions (cochées), observations, signataire.

**Files:**
- Create: `app/Http/Controllers/FicheVentilationPdfController.php`, `resources/views/pdf/fiche-ventilation.blade.php`
- Modify: `routes/web.php`, `resources/views/livewire/courrier/courrier-entite.blade.php` (lien « Imprimer la fiche » par imputation)
- Test: `tests/Feature/Courrier/FicheVentilationPdfTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Courrier/FicheVentilationPdfTest.php`:
```php
<?php

use App\Models\Agent;
use App\Models\Courrier;
use App\Models\Direction;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ctxPdf(): array
{
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $bc = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    $eDoe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction']);

    $c = Courrier::create(['numero' => 'C-77', 'objet' => 'Convocation', 'expediteur' => 'MININT', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $bc->id]);
    $imp = Imputation::create(['courrier_id' => $c->id, 'niveau' => 'dg', 'mentions' => ['execution', 'urgent'], 'observations' => 'Traiter avant vendredi', 'signataire_nom' => 'Le DG', 'saisi_par' => $bc->id]);
    $imp->destinataires()->sync([$eDoe->id]);

    return compact('bc', 'c', 'imp');
}

it('génère le PDF de la fiche de ventilation (bureau courrier)', function () {
    ['bc' => $bc, 'imp' => $imp] = ctxPdf();

    $res = $this->actingAs($bc)->get(route('imputations.fiche.pdf', $imp));

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
});

it('refuse le PDF à un utilisateur sans accès (403)', function () {
    ['imp' => $imp] = ctxPdf();
    $agent = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'role' => 'agent']);

    $this->actingAs($agent)->get(route('imputations.fiche.pdf', $imp))->assertForbidden();
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/FicheVentilationPdfTest.php`
Expected: FAIL (route/contrôleur absents).

- [ ] **Step 3: Contrôleur**

Create `app/Http/Controllers/FicheVentilationPdfController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Entite;
use App\Models\Imputation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;

class FicheVentilationPdfController extends Controller
{
    public function __invoke(Imputation $imputation)
    {
        Gate::authorize('voir', $imputation->courrier);

        $imputation->loadMissing('courrier', 'destinataires', 'entiteSource');

        // Candidats affichés sur la fiche (cases à cocher) selon le niveau.
        if ($imputation->niveau === 'direction' && $imputation->entite_source_id) {
            $candidats = Entite::where('type', 'division')->where('parent_id', $imputation->entite_source_id)->orderBy('code')->get();
        } else {
            $candidats = Entite::whereIn('type', ['direction', 'service'])->orderBy('type')->orderBy('code')->get();
        }

        $coches = $imputation->destinataires->pluck('id')->all();

        $pdf = Pdf::loadView('pdf.fiche-ventilation', [
            'imp' => $imputation,
            'courrier' => $imputation->courrier,
            'candidats' => $candidats,
            'coches' => $coches,
            'mentions' => Imputation::MENTIONS,
        ]);

        return $pdf->stream("fiche-ventilation-{$imputation->id}.pdf");
    }
}
```

- [ ] **Step 4: Vue PDF (dompdf — utiliser des tables, CSS basique)**

Create `resources/views/pdf/fiche-ventilation.blade.php`:
```blade
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #14201b; margin: 0; }
        .head { text-align: center; border-bottom: 2px solid #0d3f28; padding-bottom: 6px; margin-bottom: 10px; }
        .head .rep { font-size: 9px; letter-spacing: .5px; }
        .head .org { font-size: 14px; font-weight: bold; color: #0d3f28; margin-top: 2px; }
        .title { text-align: center; font-size: 13px; font-weight: bold; letter-spacing: 1px; margin: 8px 0 12px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 4px 6px; border: 1px solid #999; }
        .info .lbl { background: #eef3ef; font-weight: bold; width: 22%; }
        .sect { font-weight: bold; color: #0d3f28; margin: 12px 0 5px; font-size: 11px; text-transform: uppercase; }
        .grid td { padding: 3px 5px; width: 33%; vertical-align: top; }
        .box { display: inline-block; width: 10px; height: 10px; border: 1px solid #333; text-align: center; line-height: 10px; font-size: 9px; margin-right: 5px; }
        .obs { border: 1px solid #999; padding: 8px; min-height: 40px; }
        .sign { margin-top: 22px; text-align: right; }
        .sign .who { border-top: 1px solid #333; display: inline-block; padding-top: 4px; min-width: 200px; text-align: center; }
    </style>
</head>
<body>
    <div class="head">
        <div class="rep">RÉPUBLIQUE DU SÉNÉGAL — Un Peuple · Un But · Une Foi</div>
        <div class="org">Direction Générale des Élections</div>
    </div>

    <div class="title">Fiche de Ventilation</div>

    <table class="info">
        <tr><td class="lbl">N° courrier</td><td>{{ $courrier->numero }}</td><td class="lbl">Date d'arrivée</td><td>{{ $courrier->date_arrivee->format('d/m/Y') }}</td></tr>
        <tr><td class="lbl">Expéditeur</td><td>{{ $courrier->expediteur }}</td><td class="lbl">Date de départ</td><td>{{ $courrier->date_depart?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><td class="lbl">Objet</td><td colspan="3">{{ $courrier->objet }}</td></tr>
    </table>

    <div class="sect">Destinataires{{ $imp->niveau === 'direction' && $imp->entiteSource ? ' — Divisions de '.$imp->entiteSource->code : '' }}</div>
    <table class="grid">
        @foreach ($candidats->chunk(3) as $ligne)
            <tr>
                @foreach ($ligne as $e)
                    <td><span class="box">{{ in_array($e->id, $coches) ? 'X' : '' }}</span>{{ $e->code }} — {{ $e->nom }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <div class="sect">Soit transmis</div>
    <table class="grid">
        @foreach (collect($mentions)->chunk(3) as $ligne)
            <tr>
                @foreach ($ligne as $code => $libelle)
                    <td><span class="box">{{ in_array($code, $imp->mentions ?? []) ? 'X' : '' }}</span>{{ $libelle }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <div class="sect">Observations</div>
    <div class="obs">{{ $imp->observations }}</div>

    <div class="sign">
        <div class="who">
            {{ $imp->signataire_nom ?: config('dge.dg_nom') }}<br>
            <span style="font-size:9px">{{ $imp->niveau === 'dg' ? config('dge.dg_fonction') : 'Le Directeur' }}</span>
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 5: Route + lien dans la fiche**

Modify `routes/web.php` — dans le groupe `Route::middleware('auth')->group(...)` (celui existant, tout en haut), ajouter :
```php
    // PDF Fiche de Ventilation — accès contrôlé par CourrierPolicy::voir dans le contrôleur
    Route::get('/imputations/{imputation}/fiche.pdf', \App\Http\Controllers\FicheVentilationPdfController::class)->name('imputations.fiche.pdf');
```
Modify `resources/views/livewire/courrier/courrier-entite.blade.php` — dans la carte « Historique d'imputation », à l'intérieur de la boucle `@foreach ($imputations as $imp)`, ajouter un lien d'impression (par ex. juste après la ligne du titre de l'imputation) :
```blade
                <a href="{{ route('imputations.fiche.pdf', $imp) }}" target="_blank" style="float:right;font-size:12px;color:var(--green);text-decoration:none">Imprimer la fiche ↗</a>
```

- [ ] **Step 6: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/FicheVentilationPdfTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Lancer TOUTE la suite**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest`
Expected: tous verts.

- [ ] **Step 8: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat(courrier): PDF fiche de ventilation (dompdf)"
```

---

## Self-Review (effectué)

- **Couverture spec :** §7/§9 archiviste consultation globale + recherche + dates (Task 1) ✓ ; §12 PDF Fiche de Ventilation reproduisant destinataires cochés + 12 mentions + observations + signataire, adapté au niveau dg/direction (Task 2) ✓. Le détail archiviste réutilise `CourrierEntite` (policy `voir` autorise déjà archiviste — vérifié P3), avec lien retour rendu dynamique.
- **Accès :** `/archives*` derrière `role:archiviste` ; PDF derrière `auth` + `Gate::authorize('voir', $courrier)` dans le contrôleur (courrier/archiviste/chef/secrétaire d'entité destinataire OK ; agent lambda → 403). Test 403 couvre le PDF.
- **dompdf :** vue autonome (tables + CSS basique compatible dompdf, police DejaVu Sans pour les accents), pas de flex/grid CSS. Facade `Barryvdh\DomPDF\Facade\Pdf` (déjà utilisée par `OrdreMissionPdfController`). Pas d'image externe/scan dans le PDF (évite les soucis de chemin dompdf).
- **Placeholders :** aucun. **Types :** contrôleur `__invoke(Imputation)`, route model binding `{imputation}`/`{courrier}`, `CourrierEntite::render` ajoute `retour` (string). Réutilise `Imputation::MENTIONS`, `destinataires()`, `entiteSource()`, `config('dge.*')`.

## Fin du module Courrier

Après P4, le module est complet (P1 Fondations → P2 Enregistrement/Ventilation → P3 Cascade/Consultation → P4 Archives/PDF). Suites éventuelles hors périmètre : notifications (courrier reçu), délais de traitement/relances, statistiques par entité.
