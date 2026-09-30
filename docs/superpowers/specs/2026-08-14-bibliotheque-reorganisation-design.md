# Réorganisation de la bibliothèque électorale — Design

Date : 2026-08-14
Statut : validé (design), à planifier

## Contexte & problème

L'import massif du corpus électoral (dossiers locaux + SSD « doc DFC ») a rempli la
bibliothèque de ~2350 documents, mais mal rangés :

- la moitié échoue dans une rubrique fourre-tout « Divers » ;
- les types (lois, décrets, arrêtés, code électoral, guides, rapports) sont mélangés
  au lieu d'avoir chacun leur rubrique ;
- aucun classement par année à l'intérieur d'une rubrique ;
- des documents **hors-sujet** (finance, paiements, décharges, actes administratifs)
  se sont glissés dans le corpus.

## Objectif

Réorganiser **sans ré-importer** (les fichiers et les chunks RAG sont déjà en base) :

1. rubriques **par type de document** ;
2. à l'intérieur d'une rubrique, **classement par année** (sections repliables, décroissant) ;
3. **suppression définitive** des documents hors-sujet ;
4. **rangement forcé** des documents non typés vers la rubrique la plus proche
   (plus de rubrique « Divers »).

## Non-objectifs (YAGNI)

- Pas de ré-import du corpus (30 Go).
- Pas de tri manuel document par document.
- Pas de modification du moteur de recherche / RAG (continue de fonctionner).
- Pas de modification de l'enum `documents.type` (la **rubrique** porte la catégorie).

---

## 1. Taxonomie (rubriques par type)

Ensemble canonique, dans l'ordre d'affichage (`ordre`) :

| # | Rubrique | Contenu |
|---|----------|---------|
| 1 | Constitution | Constitution + préambule |
| 2 | Code électoral | Codes électoraux (toutes éditions) |
| 3 | Lois | Lois électorales |
| 4 | Ordonnances | Ordonnances |
| 5 | Décrets | Décrets |
| 6 | Arrêtés | Arrêtés |
| 7 | Circulaires & instructions | Circulaires, instructions, notes de service électorales |
| 8 | Décisions du Conseil constitutionnel | Décisions / avis du CC |
| 9 | Guides pratiques & bréviaires | Guides, bréviaires, manuels de formation |
| 10 | Rapports CENA | Rapports (annuels + scrutins) de la CENA |
| 11 | Audit du fichier électoral | Missions d'audit du fichier (MAFE, etc.) |
| 12 | Comité de veille | Rapports du comité de veille |
| 13 | Missions d'observation | Observation électorale (UE, locales…) |
| 14 | Rapports divers | Autres rapports électoraux |
| 15 | Comptes rendus & réunions | CR / PV de réunions (coordination DGE, DGE-CENA, CPDN, CTRCE…) |
| 16 | Investitures | Investitures, parrainage, cautionnement |
| 17 | Données & cartes électorales | Cartes électorales, listes de partis, répartition électeurs/bureaux |
| 18 | Textes historiques (JO 1960-1982) | Sections issues de `loi.md` (source `texte`) — **inchangé** |

Les rubriques hors de cet ensemble et vides après reclassement sont désactivées
(`actif = false`) — pas supprimées, pour ne pas casser d'éventuelles références.

L'enum `documents.type` reste `[loi, decret, reglement, rapport, circulaire, guide,
archive, ordonnance, autre]` ; mapping indicatif : arrêté→`reglement`, décision→`autre`,
compte rendu→`rapport`, constitution→`loi`, données/cartes→`archive`.

## 2. Classement par année

Nouvelle colonne `documents.annee` (`unsignedSmallInteger`, nullable, indexée).

**Extraction** (dans l'ordre) : depuis `reference`, puis `titre` — on collecte tous les
nombres à 4 chiffres dans l'intervalle **[1840, année courante + 1]** et on retient
le **plus grand** (ex. « Résultats 1963 – 2012 » → 2012 ; « élections 22 mars 2009 » → 2009).
Si `date_document` est renseignée, elle prime (`date_document.year`).
Aucune année détectée → `annee = null` → section « Non daté ».

**Affichage** : au clic sur une rubrique, les documents sont regroupés par `annee`
décroissante ; chaque année est une section repliable ; « Non daté » en dernier.

## 3. Commande `bibliotheque:reclasser`

Signature : `bibliotheque:reclasser {--appliquer} {--supprimer}`

- **`--dry-run` est le comportement par défaut** (aucune écriture) : la commande
  affiche un rapport — nb à supprimer (avec la liste), nb reclassés par rubrique,
  nb sans année.
- `--appliquer` : exécute la réassignation rubrique/type/année (écritures).
- `--supprimer` : n'a d'effet **qu'avec** `--appliquer`. La suppression définitive des
  hors-sujet (entrée DB + fichier sur le disque `public` + chunks) n'a lieu **que** si
  les **deux** drapeaux `--appliquer --supprimer` sont présents. Dans tout autre cas
  (dry-run, ou `--appliquer` seul), les hors-sujet sont seulement **listés**, jamais
  supprimés silencieusement.

Portée : uniquement les documents `source = 'fichier'`. Les documents `source = 'texte'`
(sections `loi.md`, rubrique « Textes historiques ») sont **ignorés** (ni déplacés, ni supprimés).

### 3.1 Détection hors-sujet (suppression)

Un document est hors-sujet si son titre contient un **motif finance/admin** ET **aucun
signal électoral fort**.

- Motifs finance/admin : `budget`, `paiement`, `état financier`, `financ`, `salaire`,
  `indemnit`, `facture`, `bon de commande`, `décharge`, `certificat`, `cessation`,
  `comptable`, `dépense`, `orsec`, `acte de donation`, `répertoire téléphonique`,
  `curriculum`, `cv `.
- Signaux électoraux forts (protègent de la suppression) : `élection`, `electoral`,
  `scrutin`, `vote`, `bureau de vote`, `parrainage`, `candidat`, `référendum`, `cena`,
  `recensement`, `parti`, `carte électeur`, `liste électorale`, `code électoral`.

La liste des suppressions est **toujours affichée avant exécution** (dry-run) pour revue,
car l'action est irréversible.

### 3.2 Classement (documents conservés)

Fonction pure `classer(titre, reference): {rubrique, type, annee}` — scan de mots-clés du
titre par **priorité décroissante** (du plus spécifique au plus général) :

1. `constitution` → Constitution
2. `code électoral` / `code electoral` → Code électoral
3. `ordonnance` → Ordonnances
4. `décret` / `decret` → Décrets
5. `arrêté` / `arrete` → Arrêtés
6. `circulaire` / `instruction` / `note de service` → Circulaires & instructions
7. `conseil constitutionnel` / `décision n` / `avis n` → Décisions du Conseil constitutionnel
8. `loi ` / `loi n` / `loi organique` → Lois
9. `guide` / `bréviaire` / `manuel` / `formation` → Guides pratiques & bréviaires
10. `cena` → Rapports CENA
11. `audit` / `mafe` → Audit du fichier électoral
12. `comité de veille` / `comite de veille` → Comité de veille
13. `observation` / `mission d'observation` → Missions d'observation
14. `compte rendu` / `réunion` / `procès-verbal` / `pv ` / `coordination` / `cpdn` / `ctrce` → Comptes rendus & réunions
15. `investiture` / `parrainage` / `cautionnement` → Investitures
16. `carte électorale` / `liste des partis` / `répartition` / `bureaux de vote` / `électeurs` → Données & cartes électorales
17. `rapport` / `mission` / `bilan` / `évaluation` → Rapports divers
18. **défaut (rangement forcé)** → Données & cartes électorales

Le `type` (enum) découle de la rubrique (mapping §1). Idempotent : relancer la commande
produit le même état.

## 4. UI Bibliothèque

`app/Livewire/Bibliotheque/Index.php` + `resources/views/livewire/bibliotheque/index.blade.php` :

- quand une rubrique est sélectionnée, les documents sont **groupés par `annee`**
  (décroissant, « Non daté » en dernier), chaque groupe dans une section repliable ;
- sans rubrique sélectionnée : comportement actuel (grille + recherche) conservé ;
- la liste des rubriques (colonne latérale) reflète la nouvelle taxonomie avec le
  compteur de documents ;
- charte DGB existante (classes `.card`, tokens `:root`) — pas de nouveau design system.

Décision d'implémentation : le regroupement par année en mode rubrique peut charger tous
les documents de la rubrique (sans pagination) puisqu'une rubrique reste de taille
raisonnable ; à défaut, paginer par année. À trancher au plan selon les volumes réels.

## 5. Tests (Pest)

- **Classifier** (`classer`) : cas par rubrique (loi, décret, arrêté, code, guide,
  CENA, audit, comité de veille, observation, compte rendu, investiture, données) +
  rangement forcé par défaut.
- **Extraction année** : titre mono-année, multi-années (max), date_document prioritaire,
  aucune année → null, année hors intervalle ignorée.
- **Détection hors-sujet** : finance pur supprimé ; finance + signal électoral conservé ;
  document électoral neutre conservé.
- **Commande** : dry-run n'écrit rien ; `--appliquer` réassigne ; `--supprimer` retire
  entrée + fichier + chunks ; documents `source=texte` intacts ; idempotence.
- **UI** : rubrique sélectionnée → documents groupés par année décroissante ; « Non daté »
  présent quand `annee` null.

## 6. Risques

- **Suppression irréversible** → atténuée par dry-run par défaut + double drapeau
  `--appliquer --supprimer` + liste affichée avant exécution + sauvegarde DB recommandée
  avant le run réel.
- **Faux positifs de classement** (rangement forcé) → acceptés par décision produit ;
  ajustables en affinant les mots-clés puis en relançant (idempotent).
- **Titres bruités** (scans, noms de fichiers tronqués) → année/rubrique approximatives ;
  le RAG (chunks) reste la voie de recherche fiable.
