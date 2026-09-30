# Plan 6 — Attestation de congé & État des congés (Plateforme RH DGE) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Générer l'attestation de cessation de service (PDF) après validation finale d'un congé annuel, et fournir à la DRHF un état des congés filtrable, exportable en CSV et en PDF.

**Architecture:** Un helper pur `NombreEnLettres` convertit les jours en toutes lettres (français). Le PDF de l'attestation est rendu par dompdf (déjà installé au Plan 5) depuis une vue Blade calquée sur le modèle, avec le signataire DG issu de la config. L'état des congés s'appuie sur une requête partagée testable `EtatCongesQuery` (congés `validee_rh` filtrés par direction/période), consommée par un écran Livewire RH et par deux routes d'export (CSV, PDF).

**Tech Stack:** Laravel 12, Livewire 3, Pest, Tailwind, barryvdh/laravel-dompdf. S'appuie sur Plans 1-5 : `Demande` (type `conge_annuel`, statut `validee_rh`, `date_debut`/`date_fin`, `nb_jours`, agent()), `Agent` (prenoms/noms/profession/matricule/solde_conge_jours, direction()), `config('dge.*')`, middleware `role:admin_rh`.

**Prérequis d'exécution:** créer la branche `feat/plan6-attestation` **à partir de** `feat/plan2-auth` (contient Plans 1-5) :
```bash
cd /Users/admin/dge-rh-platform
git checkout feat/plan2-auth
git checkout -b feat/plan6-attestation
```
Environnement local : PHP 8.5.5, Composer 2.9.5, MySQL 9.6 (root, sans mot de passe), base `dge_rh`. `php` émet un `Warning: Module "swoole" is already loaded` inoffensif — l'ignorer. Tests sur SQLite in-memory ; tests HTTP rendant un layout `@vite` → `$this->withoutVite();`. Toujours `cd /Users/admin/dge-rh-platform` en tête de chaque commande.

**Référence:** modèle `docs/references/modeles/CESSATION DE SERVICE congé.docx` + `docs/references/modeles/README.md`. Spec §9, §11.

**Règles métier verrouillées:**
- Attestation générée uniquement pour un `conge_annuel` au statut `validee_rh`. Imprimée par la **DRHF** (`role:admin_rh`).
- Texte : « Je soussigné, {dg_nom}, {dg_fonction}, certifie que {profession} {prenoms} {noms}, matricule de solde n° {matricule}, bénéficiaire d'un congé administratif de {nb_jours en lettres} ({nb_jours}) jours cessera service le {date_debut long FR}. L'intéressé reprendra service le {date_fin + 1 jour, long FR}. »
- État des congés : congés `validee_rh`, filtrables par direction et par période (chevauchement des dates), avec solde restant de l'agent. Export CSV + PDF.

---

## Fichiers créés/modifiés dans ce plan

- `app/Support/NombreEnLettres.php` — conversion entier → français.
- `app/Http/Controllers/AttestationCongeController.php` + `resources/views/pdf/attestation-conge.blade.php`.
- `app/Support/EtatCongesQuery.php` — requête partagée.
- `app/Livewire/Rh/EtatConges.php` + `resources/views/livewire/rh/etat-conges.blade.php`.
- `app/Http/Controllers/EtatCongesExportController.php` + `resources/views/pdf/etat-conges.blade.php`.
- `routes/web.php` (modifié), `resources/views/livewire/rh/agents-index.blade.php` ou layout RH (lien état — optionnel), `resources/views/components/layouts/rh.blade.php` (ajout lien nav « État congés »).
- `tests/Feature/Conges/*`.

---

## Task 1: Helper `NombreEnLettres` (français)

**Files:**
- Create: `app/Support/NombreEnLettres.php`
- Test: `tests/Feature/Conges/NombreEnLettresTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Conges/NombreEnLettresTest.php`:
```php
<?php

use App\Support\NombreEnLettres;

it('convertit les nombres en toutes lettres (français)', function (int $n, string $attendu) {
    expect(NombreEnLettres::convertir($n))->toBe($attendu);
})->with([
    [0, 'zéro'],
    [1, 'un'],
    [7, 'sept'],
    [15, 'quinze'],
    [20, 'vingt'],
    [21, 'vingt et un'],
    [30, 'trente'],
    [45, 'quarante-cinq'],
    [60, 'soixante'],
    [71, 'soixante et onze'],
    [75, 'soixante-quinze'],
    [80, 'quatre-vingts'],
    [81, 'quatre-vingt-un'],
    [90, 'quatre-vingt-dix'],
    [91, 'quatre-vingt-onze'],
    [100, 'cent'],
    [180, 'cent quatre-vingts'],
    [200, 'deux cents'],
    [215, 'deux cent quinze'],
    [365, 'trois cent soixante-cinq'],
]);
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Conges/NombreEnLettresTest.php`
Expected: FAIL (classe absente).

- [ ] **Step 3: Écrire le helper**

Create `app/Support/NombreEnLettres.php`:
```php
<?php

namespace App\Support;

class NombreEnLettres
{
    private const UNITES = [
        'zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
        'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    private const DIZAINES = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

    public static function convertir(int $n): string
    {
        if ($n < 0) {
            return 'moins '.self::convertir(-$n);
        }
        if ($n < 100) {
            return self::deuxChiffres($n);
        }
        if ($n < 1000) {
            $c = intdiv($n, 100);
            $reste = $n % 100;
            if ($reste === 0) {
                return $c === 1 ? 'cent' : self::UNITES[$c].' cents';
            }
            $cent = $c === 1 ? 'cent' : self::UNITES[$c].' cent';

            return $cent.' '.self::deuxChiffres($reste);
        }

        // >= 1000 (rare pour un congé) : gestion simple des milliers
        $milliers = intdiv($n, 1000);
        $reste = $n % 1000;
        $prefixe = $milliers === 1 ? 'mille' : self::convertir($milliers).' mille';

        return $reste === 0 ? $prefixe : $prefixe.' '.self::convertir($reste);
    }

    private static function deuxChiffres(int $n): string
    {
        if ($n < 20) {
            return self::UNITES[$n];
        }

        $d = intdiv($n, 10);
        $u = $n % 10;

        return match ($d) {
            2, 3, 4, 5, 6 => self::assembler(self::DIZAINES[$d], $u),
            7 => $u === 1 ? 'soixante et onze' : 'soixante-'.self::UNITES[10 + $u],
            8 => $u === 0 ? 'quatre-vingts' : 'quatre-vingt-'.self::UNITES[$u],
            9 => 'quatre-vingt-'.self::UNITES[10 + $u],
            default => self::UNITES[$n],
        };
    }

    private static function assembler(string $base, int $u): string
    {
        if ($u === 0) {
            return $base;
        }
        if ($u === 1) {
            return $base.' et un';
        }

        return $base.'-'.self::UNITES[$u];
    }
}
```

- [ ] **Step 4: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Conges/NombreEnLettresTest.php`
Expected: PASS (20 cas).

- [ ] **Step 5: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: helper NombreEnLettres (conversion française)"
```

---

## Task 2: Attestation de cessation de service (PDF)

**Files:**
- Create: `app/Http/Controllers/AttestationCongeController.php`
- Create: `resources/views/pdf/attestation-conge.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Conges/AttestationCongeTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Conges/AttestationCongeTest.php`:
```php
<?php

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ctxAttestation(string $statut = 'validee_rh'): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'role' => 'admin_rh']);
    $agent = Agent::create(['prenoms' => 'Cheikh Tidiane', 'noms' => 'DIALLO', 'matricule' => '716.398/J', 'direction_id' => $dir->id, 'statut' => 'police', 'profession' => 'Adjudant de Police', 'solde_conge_jours' => 10]);
    $demande = Demande::create(['agent_id' => $agent->id, 'type' => 'conge_annuel', 'date_debut' => '2026-03-18', 'date_fin' => '2026-04-16', 'nb_jours' => 30, 'statut' => $statut]);

    return compact('dir', 'rh', 'agent', 'demande');
}

it('la RH télécharge l’attestation d’un congé validé (PDF)', function () {
    ['rh' => $rh, 'demande' => $demande] = ctxAttestation();

    $response = $this->actingAs($rh)->get(route('conges.attestation', $demande));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('refuse l’attestation d’un congé non validé (404)', function () {
    ['rh' => $rh, 'demande' => $demande] = ctxAttestation('soumise');

    $this->actingAs($rh)->get(route('conges.attestation', $demande))->assertNotFound();
});

it('interdit l’attestation aux non admin_rh (403)', function () {
    $this->withoutVite();
    ['demande' => $demande] = ctxAttestation();
    $agentUser = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'role' => 'agent']);

    $this->actingAs($agentUser)->get(route('conges.attestation', $demande))->assertForbidden();
});

it('la vue attestation contient le texte réglementaire résolu', function () {
    ['demande' => $demande, 'agent' => $agent] = ctxAttestation();
    $demande->loadMissing('agent');

    $html = view('pdf.attestation-conge', [
        'd' => $demande,
        'agent' => $agent,
        'nbJoursLettres' => \App\Support\NombreEnLettres::convertir($demande->nb_jours),
        'dateReprise' => $demande->date_fin->copy()->addDay(),
    ])->render();

    expect($html)->toContain('Cheikh Tidiane')->toContain('DIALLO');
    expect($html)->toContain('716.398/J');
    expect($html)->toContain('trente (30)');
    expect($html)->toContain(config('dge.dg_fonction'));
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Conges/AttestationCongeTest.php`
Expected: FAIL (route/contrôleur/vue absents).

- [ ] **Step 3: Contrôleur**

Create `app/Http/Controllers/AttestationCongeController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Support\NombreEnLettres;
use Barryvdh\DomPDF\Facade\Pdf;

class AttestationCongeController extends Controller
{
    public function __invoke(Demande $demande)
    {
        abort_unless(
            $demande->type === 'conge_annuel' && $demande->statut === Demande::STATUT_VALIDEE_RH,
            404
        );

        $demande->loadMissing('agent');

        $pdf = Pdf::loadView('pdf.attestation-conge', [
            'd' => $demande,
            'agent' => $demande->agent,
            'nbJoursLettres' => NombreEnLettres::convertir((int) $demande->nb_jours),
            'dateReprise' => $demande->date_fin->copy()->addDay(),
        ]);

        return $pdf->stream("attestation-conge-{$demande->id}.pdf");
    }
}
```

- [ ] **Step 4: Vue PDF de l'attestation**

Create `resources/views/pdf/attestation-conge.blade.php`:
```blade
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #000; line-height: 1.6; }
        .entete { text-align: center; margin-bottom: 6px; }
        .titre { text-align: center; font-weight: bold; font-size: 15px; margin: 24px 0; }
        .etoiles { text-align: center; margin-bottom: 24px; }
        .corps { text-align: justify; margin: 0 10px; }
        .signature { margin-top: 60px; text-align: right; margin-right: 30px; }
    </style>
</head>
<body>
    <div class="entete">
        RÉPUBLIQUE DU SÉNÉGAL<br>
        Un Peuple – Un But – Une Foi<br>
        MINISTÈRE DE L'INTÉRIEUR ET DE LA SÉCURITÉ PUBLIQUE<br>
        DIRECTION GÉNÉRALE DES ÉLECTIONS
    </div>

    <div class="titre">ATTESTATION DE CESSATION DE SERVICE</div>
    <div class="etoiles">*******************</div>

    <div class="corps">
        Je soussigné, {{ config('dge.dg_nom') }}, {{ config('dge.dg_fonction') }},
        certifie que {{ $agent->profession ? $agent->profession.' ' : '' }}{{ $agent->prenoms }} {{ $agent->noms }},
        matricule de solde n° {{ $agent->matricule ?? '—' }},
        bénéficiaire d'un congé administratif de {{ $nbJoursLettres }} ({{ $d->nb_jours }}) jours
        cessera service le {{ $d->date_debut->locale('fr')->translatedFormat('l j F Y') }}.
        <br><br>
        L'intéressé reprendra service le {{ $dateReprise->locale('fr')->translatedFormat('l j F Y') }}.
        <br><br>
        En foi de quoi, la présente attestation lui est délivrée pour servir et valoir ce que de droit.
    </div>

    <div class="signature">
        Dakar, le {{ now()->locale('fr')->translatedFormat('j F Y') }}<br><br>
        {{ config('dge.dg_fonction') }}<br><br><br>
        <strong>{{ config('dge.dg_nom') }}</strong>
    </div>
</body>
</html>
```

- [ ] **Step 5: Route**

Modify `routes/web.php` — dans le groupe `role:admin_rh` (celui de l'espace RH / validation) ou un nouveau groupe `['auth','role:admin_rh']`, ajouter :
```php
    Route::get('/conges/{demande}/attestation', \App\Http\Controllers\AttestationCongeController::class)->name('conges.attestation');
```

- [ ] **Step 6: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Conges/AttestationCongeTest.php`
Expected: PASS (4). Note : `translatedFormat` nécessite la locale FR de Carbon (fournie avec Laravel). Si la date ressort en anglais, ce n'est pas testé au mot près (le test vérifie noms/matricule/jours/DG), donc non bloquant ; mais garder `->locale('fr')`.

- [ ] **Step 7: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: attestation de cessation de service (PDF congé)"
```

---

## Task 3: État des congés — requête partagée + écran RH + export CSV

**Files:**
- Create: `app/Support/EtatCongesQuery.php`
- Create: `app/Livewire/Rh/EtatConges.php`, `resources/views/livewire/rh/etat-conges.blade.php`
- Create: `app/Http/Controllers/EtatCongesExportController.php`
- Modify: `routes/web.php`, `resources/views/components/layouts/rh.blade.php`
- Test: `tests/Feature/Conges/EtatCongesTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Conges/EtatCongesTest.php`:
```php
<?php

use App\Livewire\Rh\EtatConges;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use App\Support\EtatCongesQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxEtat(): array
{
    $dg = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $doe = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'role' => 'admin_rh']);
    $a1 = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dg->id, 'statut' => 'police', 'solde_conge_jours' => 10]);
    $a2 = Agent::create(['prenoms' => 'Modou', 'noms' => 'FALL', 'matricule' => 'A2', 'direction_id' => $doe->id, 'statut' => 'autre', 'solde_conge_jours' => 5]);
    // congé validé DG en août
    Demande::create(['agent_id' => $a1->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-10', 'nb_jours' => 10, 'statut' => 'validee_rh']);
    // congé validé DOE en septembre
    Demande::create(['agent_id' => $a2->id, 'type' => 'conge_annuel', 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-05', 'nb_jours' => 5, 'statut' => 'validee_rh']);
    // congé NON validé (ne doit pas apparaître)
    Demande::create(['agent_id' => $a1->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-20', 'date_fin' => '2026-08-25', 'nb_jours' => 6, 'statut' => 'soumise']);

    return compact('dg', 'doe', 'rh', 'a1', 'a2');
}

it('la requête ne retourne que les congés validés dans la période', function () {
    ['dg' => $dg] = ctxEtat();

    $tous = EtatCongesQuery::pour(null, null, null);
    expect($tous)->toHaveCount(2);

    $enAout = EtatCongesQuery::pour(null, '2026-08-01', '2026-08-31');
    expect($enAout)->toHaveCount(1);

    $dgSeul = EtatCongesQuery::pour($dg->id, null, null);
    expect($dgSeul)->toHaveCount(1);
});

it('interdit l’écran état des congés aux non admin_rh (403)', function () {
    $this->withoutVite();
    $agentUser = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'role' => 'agent']);

    $this->actingAs($agentUser)->get('/rh/etat-conges')->assertForbidden();
});

it('l’écran RH liste les congés validés', function () {
    ['rh' => $rh] = ctxEtat();

    Livewire::actingAs($rh)
        ->test(EtatConges::class)
        ->assertSee('DIOP')
        ->assertSee('FALL');
});

it('exporte l’état en CSV', function () {
    ['rh' => $rh] = ctxEtat();

    $response = $this->actingAs($rh)->get('/rh/etat-conges.csv');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain('DIOP');
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Conges/EtatCongesTest.php`
Expected: FAIL (query/écran/route absents).

- [ ] **Step 3: Requête partagée**

Create `app/Support/EtatCongesQuery.php`:
```php
<?php

namespace App\Support;

use App\Models\Demande;
use Illuminate\Support\Collection;

class EtatCongesQuery
{
    /**
     * Congés annuels validés (validee_rh), filtrés par direction et par
     * chevauchement de période. Retourne une collection de Demande avec agent+direction.
     */
    public static function pour(?int $directionId, ?string $du, ?string $au): Collection
    {
        return Demande::query()
            ->with('agent.direction')
            ->where('type', 'conge_annuel')
            ->where('statut', Demande::STATUT_VALIDEE_RH)
            ->when($directionId, fn ($q) => $q->whereHas('agent', fn ($a) => $a->where('direction_id', $directionId)))
            ->when($du, fn ($q) => $q->whereDate('date_fin', '>=', $du))
            ->when($au, fn ($q) => $q->whereDate('date_debut', '<=', $au))
            ->orderBy('date_debut')
            ->get();
    }
}
```

- [ ] **Step 4: Écran Livewire**

Create `app/Livewire/Rh/EtatConges.php`:
```php
<?php

namespace App\Livewire\Rh;

use App\Models\Direction;
use App\Support\EtatCongesQuery;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class EtatConges extends Component
{
    public ?int $directionId = null;
    public ?string $du = null;
    public ?string $au = null;

    public function render()
    {
        return view('livewire.rh.etat-conges', [
            'conges' => EtatCongesQuery::pour($this->directionId, $this->du, $this->au),
            'directions' => Direction::orderBy('code')->get(),
        ]);
    }
}
```

- [ ] **Step 5: Vue de l'écran**

Create `resources/views/livewire/rh/etat-conges.blade.php`:
```blade
<div>
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-xl font-semibold">État des congés</h1>
        <div class="flex gap-2">
            <a href="{{ route('rh.etat-conges.csv', ['direction' => $directionId, 'du' => $du, 'au' => $au]) }}" class="rounded bg-gray-800 px-3 py-2 text-sm text-white">Export CSV</a>
            <a href="{{ route('rh.etat-conges.pdf', ['direction' => $directionId, 'du' => $du, 'au' => $au]) }}" target="_blank" class="rounded bg-emerald-700 px-3 py-2 text-sm text-white">Export PDF</a>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-3">
        <select wire:model.live="directionId" class="rounded border-gray-300">
            <option value="">Toutes les directions</option>
            @foreach ($directions as $d)
                <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->nom }}</option>
            @endforeach
        </select>
        <input type="date" wire:model.live="du" class="rounded border-gray-300" placeholder="Du">
        <input type="date" wire:model.live="au" class="rounded border-gray-300" placeholder="Au">
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr><th class="p-3">Agent</th><th class="p-3">Direction</th><th class="p-3">Période</th><th class="p-3">Jours</th><th class="p-3">Solde restant</th><th class="p-3"></th></tr>
            </thead>
            <tbody>
                @forelse ($conges as $c)
                    <tr class="border-t">
                        <td class="p-3">{{ $c->agent->prenoms }} {{ $c->agent->noms }}</td>
                        <td class="p-3">{{ $c->agent->direction?->code }}</td>
                        <td class="p-3">{{ $c->date_debut->format('d/m/Y') }} → {{ $c->date_fin->format('d/m/Y') }}</td>
                        <td class="p-3">{{ $c->nb_jours }}</td>
                        <td class="p-3">{{ $c->agent->solde_conge_jours }}</td>
                        <td class="p-3"><a href="{{ route('conges.attestation', $c) }}" target="_blank" class="text-emerald-700 underline">Attestation</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-4 text-center text-gray-500">Aucun congé validé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 6: Contrôleur d'export (CSV pour l'instant)**

Create `app/Http/Controllers/EtatCongesExportController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Support\EtatCongesQuery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EtatCongesExportController extends Controller
{
    public function csv(Request $request): StreamedResponse
    {
        $conges = EtatCongesQuery::pour(
            $request->integer('direction') ?: null,
            $request->query('du'),
            $request->query('au'),
        );

        return response()->streamDownload(function () use ($conges) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Agent', 'Direction', 'Debut', 'Fin', 'Jours', 'Solde restant']);
            foreach ($conges as $c) {
                fputcsv($out, [
                    $c->agent->prenoms.' '.$c->agent->noms,
                    $c->agent->direction?->code,
                    $c->date_debut->format('Y-m-d'),
                    $c->date_fin->format('Y-m-d'),
                    $c->nb_jours,
                    $c->agent->solde_conge_jours,
                ]);
            }
            fclose($out);
        }, 'etat-conges.csv', ['Content-Type' => 'text/csv']);
    }
}
```

- [ ] **Step 7: Routes + lien nav**

Modify `routes/web.php` — dans un groupe `['auth','role:admin_rh']`, ajouter :
```php
    Route::get('/rh/etat-conges', \App\Livewire\Rh\EtatConges::class)->name('rh.etat-conges');
    Route::get('/rh/etat-conges.csv', [\App\Http\Controllers\EtatCongesExportController::class, 'csv'])->name('rh.etat-conges.csv');
```
Note : la route `rh.etat-conges.pdf` est ajoutée en Task 4 ; comme la vue `etat-conges` la référence, ajouter d'abord un stub dans le même groupe pour que le rendu fonctionne :
```php
    Route::get('/rh/etat-conges.pdf', fn () => abort(501))->name('rh.etat-conges.pdf');
```
Modify `resources/views/components/layouts/rh.blade.php` — dans la `<nav>`, ajouter un lien :
```blade
                <a href="{{ route('rh.etat-conges') }}" class="hover:underline">État congés</a>
```

- [ ] **Step 8: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Conges/EtatCongesTest.php`
Expected: PASS (4).

- [ ] **Step 9: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: etat des conges (requete partagee, ecran RH, export CSV)"
```

---

## Task 4: Export PDF de l'état des congés

**Files:**
- Modify: `app/Http/Controllers/EtatCongesExportController.php`
- Create: `resources/views/pdf/etat-conges.blade.php`
- Modify: `routes/web.php` (remplacer le stub `rh.etat-conges.pdf`)
- Test: `tests/Feature/Conges/EtatCongesPdfTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Conges/EtatCongesPdfTest.php`:
```php
<?php

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exporte l’état des congés en PDF', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'role' => 'admin_rh']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 10]);
    Demande::create(['agent_id' => $agent->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-10', 'nb_jours' => 10, 'statut' => 'validee_rh']);

    $response = $this->actingAs($rh)->get('/rh/etat-conges.pdf');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('interdit l’export PDF aux non admin_rh (403)', function () {
    $this->withoutVite();
    $agentUser = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'role' => 'agent']);

    $this->actingAs($agentUser)->get('/rh/etat-conges.pdf')->assertForbidden();
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Conges/EtatCongesPdfTest.php`
Expected: FAIL (le stub renvoie 501).

- [ ] **Step 3: Méthode `pdf` sur le contrôleur**

Modify `app/Http/Controllers/EtatCongesExportController.php` — ajouter l'import et la méthode :
```php
use Barryvdh\DomPDF\Facade\Pdf;
```
```php
    public function pdf(Request $request)
    {
        $conges = EtatCongesQuery::pour(
            $request->integer('direction') ?: null,
            $request->query('du'),
            $request->query('au'),
        );

        $pdf = Pdf::loadView('pdf.etat-conges', [
            'conges' => $conges,
            'du' => $request->query('du'),
            'au' => $request->query('au'),
        ]);

        return $pdf->stream('etat-conges.pdf');
    }
```

- [ ] **Step 4: Vue PDF de l'état**

Create `resources/views/pdf/etat-conges.blade.php`:
```blade
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #000; }
        h1 { text-align: center; font-size: 15px; }
        .periode { text-align: center; margin-bottom: 12px; color: #333; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #666; padding: 5px; text-align: left; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h1>État des congés — DGE</h1>
    <div class="periode">
        @if ($du || $au) Période : {{ $du ?? '…' }} au {{ $au ?? '…' }} @endif
    </div>

    <table>
        <thead>
            <tr><th>Agent</th><th>Direction</th><th>Début</th><th>Fin</th><th>Jours</th><th>Solde restant</th></tr>
        </thead>
        <tbody>
            @foreach ($conges as $c)
                <tr>
                    <td>{{ $c->agent->prenoms }} {{ $c->agent->noms }}</td>
                    <td>{{ $c->agent->direction?->code }}</td>
                    <td>{{ $c->date_debut->format('d/m/Y') }}</td>
                    <td>{{ $c->date_fin->format('d/m/Y') }}</td>
                    <td>{{ $c->nb_jours }}</td>
                    <td>{{ $c->agent->solde_conge_jours }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
```

- [ ] **Step 5: Remplacer le stub de route**

Modify `routes/web.php` — remplacer la ligne stub `rh.etat-conges.pdf` par :
```php
    Route::get('/rh/etat-conges.pdf', [\App\Http\Controllers\EtatCongesExportController::class, 'pdf'])->name('rh.etat-conges.pdf');
```

- [ ] **Step 6: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Conges/EtatCongesPdfTest.php`
Expected: PASS (2).

- [ ] **Step 7: Lancer TOUTE la suite**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest`
Expected: tous verts (Plans 1-6).

- [ ] **Step 8: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: export PDF de l'etat des conges"
```

---

## Self-Review (effectué)

- **Couverture spec/exigences :** §9/§11 attestation de cessation de service générée après `validee_rh`, nb jours en lettres + date reprise (`date_fin`+1), signataire DG configurable (Tasks 1-2) ✓ ; réservée à la DRHF, refusée si non validée (Task 2 autz + 404) ✓ ; état des congés filtrable + export CSV + PDF (Tasks 3-4) ✓.
- **Placeholders :** aucun. Le stub `rh.etat-conges.pdf` (Task 3) est une route réelle temporaire, remplacée en Task 4.
- **Cohérence types :** `NombreEnLettres::convertir(int): string` (Tasks 1-2). `EtatCongesQuery::pour(?int, ?string, ?string): Collection` partagée entre écran (Task 3), export CSV (Task 3) et export PDF (Task 4). Routes `conges.attestation`, `rh.etat-conges`, `rh.etat-conges.csv`, `rh.etat-conges.pdf` nommées identiquement (vues, tests). Statut `validee_rh` = `Demande::STATUT_VALIDEE_RH`. dompdf réutilisé (Plan 5).

## Dépendances pour les plans suivants

Plan 7 (Dashboards) : réutilisera `EtatCongesQuery` et des agrégats sur `demandes` (par direction, statut, type) + taux d'absence, avec la même protection par rôle.
