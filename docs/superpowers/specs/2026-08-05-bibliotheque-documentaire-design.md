# Module Bibliothèque documentaire (Médiathèque électorale) — Design

**Statut:** approuvé (design). Module A d'un ensemble de 2 (A = documents ; B = résultats électoraux, spec séparé à venir).

## Objectif

Offrir à **tous les agents** de la DGE une bibliothèque virtuelle façon **encyclopédie électorale** : retrouver facilement, par thème/rubrique et par recherche, tous les documents de référence (lois, décrets, règlements, rapports, circulaires, guides, archives). Publication par l'**archiviste** et le **super-admin**. UI moderne et attirante (guidée par le skill `ui-ux-pro-max`), sous la charte DGB (vert `#309966` / navy `#193761` / Open Sans).

## Périmètre (YAGNI)

- **Inclus** : rubriques configurables (CRUD), documents (PDF uploadé **ou** lien externe) avec métadonnées, recherche instantanée par mot-clé + filtres (rubrique, type), consultation par tout agent (lecteur PDF inline + téléchargement), gestion réservée archiviste+admin.
- **Exclu** (autre module/plus tard) : les **résultats électoraux** (Module B — données structurées de `senegal-elections`) ; versioning de documents ; commentaires/annotations ; workflow de validation éditoriale.
- **Point d'intégration** : une tuile « Résultats des élections » sur l'accueil de la bibliothèque pointera vers l'explorateur du Module B quand il existera (simple lien, pas dans ce module).

## Modèle de données

**`rubriques`**
- `id`, `nom` (unique), `slug` (unique), `description` (nullable), `icone` (nullable — nom d'icône/emoji), `ordre` (int, tri), `actif` (bool), timestamps.

**`documents`**
- `id`, `titre`, `rubrique_id` → rubriques (nullOnDelete), `type` enum(`loi`,`decret`,`reglement`,`rapport`,`circulaire`,`guide`,`archive`,`autre`), `reference` (nullable — ex. « Loi n°2021-35 »), `date_document` (date, nullable), `resume` (text, nullable), `mots_cles` (string, nullable — libre, séparés par virgules ; utilisé en recherche), `source` enum(`fichier`,`lien`), `fichier_path` (nullable — disque public), `url` (nullable), `publie_par` → users, `actif` (bool, défaut true), timestamps.
- Contrainte applicative : `source=fichier` ⇒ `fichier_path` requis ; `source=lien` ⇒ `url` requis.

**Modèles Eloquent** : `Rubrique` (hasMany documents), `Document` (belongsTo rubrique, publiePar). `Document::fichierUrl()` → `/storage/...` relatif (jamais `Storage::url()`). `Document::estPdf()`.

## Accès (routes)

- **Consultation** — `role`-agnostique, tout compte connecté+vérifié :
  - `/bibliotheque` (accueil : recherche + rubriques + résultats) — `Bibliotheque\Index`.
  - `/bibliotheque/document/{document}` (fiche + lecteur) — `Bibliotheque\FicheDocument`.
  - Middleware : `['auth','verified','password.change']`.
- **Gestion** — archiviste + super-admin (le middleware `role` laisse déjà passer `admin`) :
  - `/bibliotheque/gerer/rubriques` — `Bibliotheque\Gestion\Rubriques` (CRUD).
  - `/bibliotheque/gerer/documents` — `Bibliotheque\Gestion\Documents` (CRUD + upload/lien).
  - Middleware : `['auth','verified','password.change','role:archiviste']` (le super-admin `admin` passe via le bypass EnsureRole).
- **Navigation** : lien « Bibliothèque » visible par **tous** dans la barre latérale (section Personnel ou dédiée). Liens « Gérer la bibliothèque » visibles pour archiviste + admin.

## Recherche & filtres

- Recherche instantanée (Livewire `wire:model.live.debounce.300ms`) sur `titre`, `resume`, `mots_cles`, `reference`.
- Filtres : rubrique (select), type (select), tri par `date_document` desc (défaut) / titre.
- Seuls les documents `actif=true` sont visibles côté consultation ; le gestionnaire voit tout.

## UI (façon encyclopédie — skill ui-ux-pro-max)

- **Accueil** (`Index`) : bandeau titre + **grande barre de recherche** centrale (hero) ; en dessous, **tuiles de rubriques** (grille responsive `repeat(auto-fit,minmax(220px,1fr))`, chaque tuile = icône + nom + compteur de documents). Sous les tuiles / après recherche : **liste de documents** en cartes (titre, badge rubrique + type, date, résumé tronqué, action Consulter/Télécharger ou Ouvrir le lien ↗).
- **Fiche document** (`FicheDocument`) : métadonnées + lecteur **PDF inline** (iframe, comme les scans courrier) pour `source=fichier` ; bouton « Ouvrir le lien » pour `source=lien` ; bouton « Télécharger ».
- Styling : charte DGB, cartes `.card`, boutons `.btn`. Le skill `ui-ux-pro-max` guide l'esthétique (hiérarchie, tuiles thématiques attrayantes, états vides pédagogiques, accessibilité AA — cohérent avec le reste de la plateforme).

## Composants Livewire

- `Bibliotheque\Index` — recherche + rubriques (tuiles) + résultats filtrés.
- `Bibliotheque\FicheDocument` — consultation d'un document.
- `Bibliotheque\Gestion\Rubriques` — CRUD rubriques (nom, description, icône, ordre, actif) + suppression bloquée si documents rattachés (ou nullOnDelete → documents « sans rubrique »).
- `Bibliotheque\Gestion\Documents` — CRUD documents : formulaire (titre, rubrique, type, référence, date, résumé, mots-clés, source fichier/lien + upload `WithFileUploads` mimes:pdf max 20 Mo, ou url), liste avec recherche, activer/désactiver, supprimer.

## Gestion des fichiers

- Upload PDF sur le disque `public`, dossier `bibliotheque`. `fichier_path` relatif. Suppression du fichier physique à la suppression du document.
- `Document::fichierUrl()` = `'/storage/'.ltrim($fichier_path,'/')`.

## Extraction texte + chunks (pour l'ancrage de l'Assistant Claude)

Ajout requis par le Module Assistant Claude (spec 2026-08-06) — l'ancrage RAG interroge le **texte** des documents, pas les PDF :

- À l'ingestion d'un document `source=fichier`, extraire le texte (pdftotext / `Smalot\PdfParser`) et le découper en **chunks** (~500–800 mots, léger chevauchement).
- Table **`document_chunks`** : `id`, `document_id` → documents (cascade), `ordre` (int), `contenu` (longtext), index **FULLTEXT** sur `contenu`.
- Réindexation à la mise à jour / suppression du document. Seuls les documents `actif=true` sont exploités par l'outil `rechercher_bibliotheque`.
- Cette étape peut être différée à la task 4 de l'Assistant (l'ancrage) : la bibliothèque fonctionne sans, l'ancrage s'active quand les chunks existent.

## Sources de données réelles & import en masse (06/08/2026)

Corpus fourni par le client : `~/Desktop/MES PROJET/loi.md` (recueil ~40 textes électoraux historiques, séparés par titres `## N.`) + dossier `~/Documents/projet eletion` (19 PDF, 3 docx, 3 jpg : Code électoral 2021, éditions 2014/2017/2018, refonte 2016, révision 2017, CNI biométrique, rapports CENA 2010-2012, présidentielle 2019, PROJET CODE 2021.docx).

**Réalité extraction texte** (vérifiée) : loi.md ✅, PROJET CODE 2021.docx ✅ (259k car = texte du Code 2021 actuel), Édition 2018 ✅, loi 2016-10 ✅ ; mais Code 2021 JO 7442, Décret 2021-1196, Revue 2011, présidentielle 2019, éd. 2017, décret refonte 2016, rapports CENA = **scans sans couche texte** (≤100 car).

**Décisions :**
1. **Nouveau type de source `texte`** sur `documents` : enum source devient (`fichier`,`lien`,`texte`) ; colonne **`contenu`** (longtext, nullable) pour le markdown. La fiche rend le markdown ; ces documents sont chunkés directement. Utilisé pour chaque `##` de loi.md (**1 document par texte**, ~40 documents ; type loi/décret/ordonnance inféré du titre).
2. **Commande d'import** `php artisan bibliotheque:importer {--loi=} {--dossier=}` — idempotente (upsert par référence/titre) :
   - Parse loi.md en sections `##` → documents `source=texte`, `contenu`=markdown de la section, type inféré, rubrique inférée, puis chunking.
   - Parcourt le dossier : copie chaque PDF/docx sur le disque `public/bibliotheque`, crée un document `source=fichier`. **Extrait le texte** (PDF via `pdftotext`/poppler ; docx via `phpoffice/phpword`) ; si texte extrait > **seuil (800 car)** → chunking, sinon document **sans chunks** (scan → consultable/téléchargeable, recherche sur métadonnées uniquement). Les `.jpg` et fichiers temporaires `~$*` sont ignorés.
3. **Extraction** encapsulée dans un service `App\Support\Bibliotheque\ExtracteurTexte` (pdf/docx/texte) + `Chunker` (découpe ~800 mots, léger chevauchement). Dépendances : `poppler-utils` (pdftotext — présent en local ; sur le VPS `apt install poppler-utils`) + composer `phpoffice/phpword`.
4. **OCR différé** (hors périmètre) : les scans restent non cherchables en plein texte ; option future Tesseract (français) pour générer leurs chunks.

**Rubriques de départ (seed) :** Code électoral · Lois · Décrets & règlements · Ordonnances · Rapports CENA · Textes historiques (JO 1960-1982) · Archives. Mapping : loi.md→Textes historiques/Lois/Décrets/Ordonnances (selon titre) ; Code 2021 + éditions + PROJET CODE→Code électoral ; décrets→Décrets & règlements ; rapports CENA→Rapports CENA ; présidentielle 2019 + revue 2011→Archives.

## Tests (Pest)

- Rubrique/Document : création, `fichierUrl()`, contrainte source (fichier vs lien).
- Accès : consultation ouverte à un agent connecté ; gestion → 403 pour un agent, OK archiviste + admin.
- Recherche : filtre par mot-clé / rubrique / type ; seuls actifs visibles côté consultation.
- Upload : `Storage::fake('public')`, mimes:pdf, `fichier_path` renseigné ; lien : url requise.

## Décomposition en tasks (indicatif pour le plan)

1. Données + modèles (migrations rubriques/documents, modèles, seeder de rubriques de départ : Lois, Décrets, Règlements, Rapports, Circulaires, Guides, Archives).
2. Consultation (Index recherche+tuiles+résultats, FicheDocument + lecteur PDF) + route + nav.
3. Gestion (CRUD rubriques + CRUD documents avec upload/lien) + routes + nav gestion.
4. Passe design `ui-ux-pro-max` (tuiles encyclopédie, accueil attrayant, états vides) + polish.
