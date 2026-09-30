<?php

use App\Http\Controllers\AttestationCongeController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\DocumentDemandeController;
use App\Http\Controllers\EtatCongesExportController;
use App\Http\Controllers\FicheVentilationPdfController;
use App\Http\Controllers\OrdreMissionPdfController;
use App\Livewire\Admin\Assistant\Gestion as AssistantGestion;
use App\Livewire\Admin\ComptesSysteme;
use App\Livewire\AgentProfil;
use App\Livewire\Annuaire;
use App\Livewire\Assistant\Index as AssistantIndex;
use App\Livewire\Auth\ChangerMotDePasse;
use App\Livewire\Auth\EmailRequis;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\MotDePasseOublie;
use App\Livewire\Auth\ReinitialiserMotDePasse;
use App\Livewire\Auth\TwoFactor;
use App\Livewire\Bibliotheque\FicheDocument;
use App\Livewire\Bibliotheque\Gestion\Documents as GestionDocuments;
use App\Livewire\Bibliotheque\Gestion\Rubriques as GestionRubriques;
use App\Livewire\Bibliotheque\Index as BibliothequeIndex;
use App\Livewire\Courrier\Archives;
use App\Livewire\Courrier\CourrierEntite;
use App\Livewire\Courrier\FicheCourrier;
use App\Livewire\Courrier\MesCourriers;
use App\Livewire\Courrier\NouveauCourrier;
use App\Livewire\Courrier\Registre;
use App\Livewire\Demandes\MesDemandes;
use App\Livewire\Demandes\NouvelleDemande;
use App\Livewire\Missions\MesMissions;
use App\Livewire\Missions\NouvelleMission;
use App\Livewire\Rh\AgentForm;
use App\Livewire\Rh\AgentsIndex;
use App\Livewire\Rh\DirectionsManager;
use App\Livewire\Rh\EntitesManager;
use App\Livewire\Rh\EtatConges;
use App\Livewire\Rh\TableauBord;
use App\Livewire\Validation\FileChef;
use App\Livewire\Validation\FileRh;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/two-factor', TwoFactor::class)->name('two-factor.show');
    Route::get('/email-requis', EmailRequis::class)->name('email.requis');
    Route::get('/mot-de-passe-oublie', MotDePasseOublie::class)->name('password.request');
    Route::get('/reinitialiser-mot-de-passe/{token}', ReinitialiserMotDePasse::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::get('/verifier-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    Route::get('/changer-mot-de-passe', ChangerMotDePasse::class)->name('password.change');
});

Route::middleware(['auth', 'verified', 'password.change'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::get('/assistant', AssistantIndex::class)->name('assistant');

    // Bibliothèque électorale : consultation (recherche + rubriques) et fiche document.
    Route::get('/bibliotheque', BibliothequeIndex::class)->name('bibliotheque');
    Route::get('/bibliotheque/document/{document}', FicheDocument::class)->name('bibliotheque.document');

    Route::get('/demandes', MesDemandes::class)->name('demandes.mes');
    Route::get('/demandes/nouvelle', NouvelleDemande::class)->name('demandes.nouvelle');

    // Profil agent (lecture seule) — autorisation via AgentPolicy dans le composant
    Route::get('/agents/{agent}/profil', AgentProfil::class)->name('agents.profil');

    // Document PDF d'une demande validée (congé ou permission) — proprietaire ou DRHF
    Route::get('/demandes/{demande}/document', DocumentDemandeController::class)->name('demandes.document');

    // PDF Fiche de Ventilation — accès contrôlé par CourrierPolicy::voir dans le contrôleur
    Route::get('/imputations/{imputation}/fiche.pdf', FicheVentilationPdfController::class)->name('imputations.fiche.pdf');
});

// Annuaire : DG + chef de direction (sa direction) + admin RH
Route::middleware(['auth', 'verified', 'password.change', 'role:dg,chef_direction,admin_rh'])->group(function () {
    Route::get('/annuaire', Annuaire::class)->name('annuaire');
});

Route::middleware(['auth', 'verified', 'password.change', 'role:admin_rh'])->prefix('rh')->name('rh.')->group(function () {
    Route::get('/agents', AgentsIndex::class)->name('agents.index');
    Route::get('/agents/nouveau', AgentForm::class)->name('agents.create');
    Route::get('/agents/{agent}/modifier', AgentForm::class)->name('agents.edit');
    Route::get('/directions', DirectionsManager::class)->name('directions.index');
});

Route::middleware(['auth', 'verified', 'password.change', 'role:chef_direction'])->group(function () {
    Route::get('/validation/chef', FileChef::class)->name('validation.chef');
});

Route::middleware(['auth', 'verified', 'password.change', 'role:admin_rh'])->group(function () {
    Route::get('/rh/tableau-bord', TableauBord::class)->name('rh.tableau-bord');
    Route::get('/validation/rh', FileRh::class)->name('validation.rh');
    Route::get('/conges/{demande}/attestation', AttestationCongeController::class)->name('conges.attestation');
    Route::get('/rh/etat-conges', EtatConges::class)->name('rh.etat-conges');
    Route::get('/rh/etat-conges.csv', [EtatCongesExportController::class, 'csv'])->name('rh.etat-conges.csv');
    Route::get('/rh/etat-conges.pdf', [EtatCongesExportController::class, 'pdf'])->name('rh.etat-conges.pdf');
    // Structure organisationnelle (directions / bureaux / divisions + parent + chef/secrétaire) : gérée par la RH.
    Route::get('/rh/entites', EntitesManager::class)->name('rh.entites');
});

Route::middleware(['auth', 'verified', 'password.change', 'role:secretaire'])->group(function () {
    Route::get('/missions', MesMissions::class)->name('missions.mes');
    Route::get('/missions/nouvelle', NouvelleMission::class)->name('missions.nouvelle');
    Route::get('/missions/{demande}/imprimer', OrdreMissionPdfController::class)->name('missions.imprimer');
});

Route::middleware(['auth', 'verified', 'password.change', 'role:dg'])->group(function () {
    Route::get('/dg', App\Livewire\Dg\TableauBord::class)->name('dg.tableau-bord');
});

Route::middleware(['auth', 'verified', 'password.change', 'role:courrier'])->group(function () {
    Route::get('/courriers', Registre::class)->name('courriers.registre');
    Route::get('/courriers/nouveau', NouveauCourrier::class)->name('courriers.nouveau');
    Route::get('/courriers/{courrier}', FicheCourrier::class)->name('courriers.fiche');
});

// Consultation des courriers de son entité : tout utilisateur qui gère une entité
// (chef/secrétaire de direction, service, bureau OU division). Le composant scope
// aux entités gérées, CourrierEntite autorise via CourrierPolicy::voir.
Route::middleware(['auth', 'verified', 'password.change'])->group(function () {
    Route::get('/mes-courriers', MesCourriers::class)->name('mes-courriers');
    Route::get('/mes-courriers/{courrier}', CourrierEntite::class)->name('mes-courriers.fiche');
});

Route::middleware(['auth', 'verified', 'password.change', 'role:archiviste'])->group(function () { 
    Route::get('/archives', Archives::class)->name('archives');
    Route::get('/archives/{courrier}', CourrierEntite::class)->name('archives.fiche');
    Route::get('/bibliotheque/gerer/rubriques', GestionRubriques::class)->name('bibliotheque.gerer.rubriques');
    Route::get('/bibliotheque/gerer/documents', GestionDocuments::class)->name('bibliotheque.gerer.documents');
});

// Super-admin : provisioning des comptes système (RH, courrier, archives, DG).
Route::middleware(['auth', 'verified', 'password.change', 'role:admin'])->group(function () {
    Route::get('/admin/comptes', ComptesSysteme::class)->name('admin.comptes');
    Route::get('/admin/assistant', AssistantGestion::class)->name('admin.assistant');
});
