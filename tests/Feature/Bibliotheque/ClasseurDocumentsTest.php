<?php

use App\Support\Bibliotheque\ClasseurDocuments;

beforeEach(fn () => $this->c = new ClasseurDocuments());

it('extrait l année la plus récente du titre', function () {
    expect($this->c->annee('Résultats Elections Presidentielles 1963 - 2012'))->toBe(2012);
    expect($this->c->annee('élections du 22 mars 2009'))->toBe(2009);
});

it('donne la priorité à date_document', function () {
    expect($this->c->annee('rapport 2010', null, new DateTime('2016-03-20')))->toBe(2016);
});

it('renvoie null sans année plausible', function () {
    expect($this->c->annee('Code électoral'))->toBeNull();
    expect($this->c->annee('document 3012'))->toBeNull(); // hors intervalle
    expect($this->c->annee('vieux texte 1801'))->toBeNull();        // matche 1[89]\d{2} mais < 1840
    expect($this->c->annee('anticipation an 2099'))->toBeNull();    // matche 20\d{2} mais > année courante + 1
});

it('détecte les documents hors-sujet', function () {
    expect($this->c->estHorsSujet('ETAT DE PAIEMENT APPUI FINANCIER 2012'))->toBeTrue();
    expect($this->c->estHorsSujet('Certificat de cessation de service'))->toBeTrue();
    expect($this->c->estHorsSujet('Budget 2013'))->toBeTrue();
});

it('protège les documents finance à signal électoral', function () {
    expect($this->c->estHorsSujet('Budget de la CENA pour les élections 2012'))->toBeFalse();
    expect($this->c->estHorsSujet('Résultats du scrutin présidentiel'))->toBeFalse();
});

it('protège les documents électoraux administratifs', function () {
    expect($this->c->estHorsSujet("Décharge de distribution des cartes d'électeurs 2019"))->toBeFalse();
    expect($this->c->estHorsSujet('Certificat de radiation d un électeur'))->toBeFalse();
});

it('classe par type via mots-clés du titre', function () {
    expect($this->c->rubrique('Décret n° 2014-01 portant convocation'))->toBe('Décrets');
    expect($this->c->rubrique('Arrêté modifiant la carte électorale'))->toBe('Arrêtés');
    expect($this->c->rubrique('CODE ELECTORAL 2012'))->toBe('Code électoral');
    expect($this->c->rubrique('Loi organique n° 2017-01'))->toBe('Lois');
    expect($this->c->rubrique('Guide pratique du bureau de vote'))->toBe('Guides pratiques & bréviaires');
    expect($this->c->rubrique('Rapport Annuel CENA 2015'))->toBe('Rapports CENA');
    expect($this->c->rubrique('Mission d Audit du Fichier Electoral 2010'))->toBe('Audit du fichier électoral');
    expect($this->c->rubrique('Rapport du comité de veille 2012'))->toBe('Comité de veille');
    expect($this->c->rubrique('Mission d observation UE 2012'))->toBe('Missions d\'observation');
    expect($this->c->rubrique('Compte rendu réunion coordination DGE'))->toBe('Comptes rendus & réunions');
    expect($this->c->rubrique('Rapport général sur le parrainage'))->toBe('Investitures');
    expect($this->c->rubrique('Liste des Partis Politiques 2012'))->toBe('Données & cartes électorales');
});

it('range par défaut les non typés vers Données & cartes électorales', function () {
    expect($this->c->rubrique('EXPO DU TRONE'))->toBe('Données & cartes électorales');
});

it('mappe la rubrique vers un type de l enum', function () {
    expect($this->c->typePourRubrique('Décrets'))->toBe('decret');
    expect($this->c->typePourRubrique('Rapports CENA'))->toBe('rapport');
    expect($this->c->typePourRubrique('Guides pratiques & bréviaires'))->toBe('guide');
    expect($this->c->typePourRubrique('Constitution'))->toBe('loi');
});

it('ne supprime pas les documents à certificat administratif ni les comptes rendus', function () {
    expect($this->c->estHorsSujet('Note sur le certificat administratif'))->toBeFalse();
    expect($this->c->estHorsSujet('Compte rendu reunion comite de suivi certificat administratif'))->toBeFalse();
    expect($this->c->estHorsSujet('Indemnités resp bureaux électoraux'))->toBeFalse();
    // vrais hors-sujet toujours détectés :
    expect($this->c->estHorsSujet('Certificat de cessation de service'))->toBeTrue();
    expect($this->c->estHorsSujet('Budget 2013'))->toBeTrue();
    expect($this->c->estHorsSujet('facture Monsieur BA'))->toBeTrue();
});

it('classe les communiqués et discours', function () {
    expect($this->c->rubrique('Communiqué relatif au Sénat'))->toBe('Communiqués & discours');
    expect($this->c->rubrique('Discours du Directeur Général'))->toBe('Communiqués & discours');
    expect($this->c->rubrique('Allocution du DC au séminaire CENA'))->toBe('Communiqués & discours');
});

it('détecte les données carte/fichier supprimables', function () {
    expect($this->c->estDonneeSupprimable('CARTE ELECTORALE ETRANGER 2019'))->toBeTrue();
    expect($this->c->estDonneeSupprimable('CARTE ELECTORALE-TERRITOIRE 2019'))->toBeTrue();
    expect($this->c->estDonneeSupprimable('Tableau repartition des electeurs des lieux et bureaux de vote 2019'))->toBeTrue();
    // documents de référence protégés (jamais supprimés) :
    expect($this->c->estDonneeSupprimable("Mission d'Audit du Fichier Electoral Senegal 2010 Rapport Final"))->toBeFalse();
    expect($this->c->estDonneeSupprimable("Communiqué sur l'audit du fichier électoral"))->toBeFalse();
    expect($this->c->estDonneeSupprimable('H3 Textes Legislatifs Refonte Totale du fichier electoral 2006'))->toBeFalse();
    expect($this->c->estDonneeSupprimable('Lettre de transmission de la carte électorale'))->toBeFalse();
    // vrais documents de référence non-data :
    expect($this->c->estDonneeSupprimable('Rapport Annuel CENA 2015'))->toBeFalse();
    expect($this->c->estDonneeSupprimable('Code électoral 2012'))->toBeFalse();
});

it('détecte le junk haute-confiance', function () {
    expect($this->c->estJunk('Doc1'))->toBeTrue();
    expect($this->c->estJunk('Scan_Doc0002'))->toBeTrue();
    expect($this->c->estJunk('Copie (2) de Document DOE'))->toBeTrue();
    expect($this->c->estJunk('01 DAKAR MR CA'))->toBeTrue();
    expect($this->c->estJunk("Fabrice CISS demande d'emploi"))->toBeTrue();
    expect($this->c->estJunk('Planning congés de la DFC'))->toBeTrue();
    expect($this->c->estJunk('TEXTE A SUPPRIMER'))->toBeTrue();
});

it('ne juge pas junk un document électoral même mal nommé', function () {
    expect($this->c->estJunk('01 DAKAR resultats election presidentielle'))->toBeFalse(); // signal "election"
    expect($this->c->estJunk('Copie de la loi électorale 2017'))->toBeFalse();             // signal "loi "/"electora"
    expect($this->c->estJunk('Rapport CENA 2015'))->toBeFalse();
    expect($this->c->estJunk('Journal Officiel 05 juillet 2018'))->toBeFalse();            // signal "journal officiel"
    expect($this->c->estJunk('Arrêts Cour d Appel élections municipales'))->toBeFalse();
});
