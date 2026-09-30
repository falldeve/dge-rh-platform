# Modèles de documents officiels DGE

Sources fournies par le client (`.docx` dans ce dossier). Utilisés par Plan 5 (ordre de mission)
et Plan 6 (attestation de congé). **Signataire des deux documents : le Directeur Général** —
son nom et sa fonction sont configurables (setting `dg_nom`, `dg_fonction`).

## 1. Attestation de cessation de service (congé) — `CESSATION DE SERVICE congé.docx`

Texte type :

> **ATTESTATION DE CESSATION DE SERVICE**
> Je soussigné, Monsieur **{{ dg_nom }}**, {{ dg_fonction }}, certifie que
> {{ grade/profession }} **{{ prenoms }} {{ noms }}**, matricule de solde n° **{{ matricule }}**,
> bénéficiaire d'un congé administratif de **{{ nb_jours_lettres }} ({{ nb_jours }})** jours
> cessera service le **{{ date_debut_longue }}**.
> L'intéressé reprendra service le **{{ date_reprise_longue }}**.
> En foi de quoi, la présente attestation lui est délivrée pour servir et valoir ce que de droit.
> Dakar, le {{ date_edition }}

Champs à alimenter :
- `dg_nom`, `dg_fonction` — setting global.
- `grade/profession`, `prenoms`, `noms`, `matricule` — depuis l'agent.
- `nb_jours` (chiffres) + `nb_jours_lettres` (en toutes lettres, FR).
- `date_debut_longue` = `demande.date_debut` en format long FR (ex. « mercredi 18 mars 2020 »).
- `date_reprise_longue` = `demande.date_fin + 1 jour` en format long FR.
- `date_edition` = date d'impression.

Génération : après validation DRHF finale (`statut = validee_rh`) d'un congé annuel.

## 2. Ordre de mission — `Ordre de Mission DGE.docx`

Champs (formulaire) :
- **Prénom et nom** — agent concerné (le missionnaire).
- **Matricule** — agent.
- **Fonction** — agent.
- **Indice**, **Groupe** — meta (saisis par le secrétaire).
- **Se rendre à** — `meta.destination`.
- **Motif** — `meta.motif` (ou `demande.motif`).
- **Date de départ** = `demande.date_debut`, **Date de retour** = `demande.date_fin`.
- **Moyen de transport** — `meta.moyen_transport`.
- **Imputation budgétaire des frais de transport / indemnités** — `meta.imputation`.
- **Chapitre**, **Article** — `meta.chapitre`, `meta.article`.
- Pied : « Dakar, le {{ date_edition }} » + signature DG.

Génération : par le secrétaire de direction, à la création de l'ordre de mission (pas de workflow).
