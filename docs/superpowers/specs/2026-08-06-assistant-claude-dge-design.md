# Module Assistant Claude DGE — Design

**Statut:** approuvé (design). Assistant conversationnel Claude intégré, réservé/gradué par agent. Couplé au Module Bibliothèque documentaire (2026-08-05) qui fournit la base de connaissances pour l'ancrage.

## Objectif

Offrir aux agents de la DGE un **espace de chat Claude** intégré à la plateforme (façon claude.ai interne), pour les assister dans leurs tâches allouées : rédiger, résumer, traduire, corriger, analyser des documents, chercher sur le web, et interroger les textes officiels de la DGE. Deux niveaux : un **niveau gratuit** ouvert à tous, un **niveau premium** débloqué par le super-admin avec un budget de crédits réparti par l'admin.

## Compte & facturation (tranché)

- **Pas d'abonnement claude.ai** (Pro/Max = usage perso, aucun accès API). L'app utilise le **Claude Developer Platform (API)**, facturé **à l'usage** (au token).
- **Compte organisation** dédié sur `console.anthropic.com`, indépendant de tout abonnement personnel. Recharge **prépayée** (borne la dépense) ou facturation mensuelle.
- **Clé API** dans le `.env` du VPS (`ANTHROPIC_API_KEY`), jamais commitée. Config : `ANTHROPIC_MODEL_PREMIUM=claude-sonnet-5`, `ANTHROPIC_MODEL_GRATUIT=claude-haiku-4-5`.
- **Zero-Data-Retention** à demander/activer avec Anthropic pour l'organisation (données gouvernementales).
- **Rate limits** : compte neuf = quotas bas ; demander une hausse à Anthropic avant déploiement multi-agents.
- Rappel : la **facture Anthropic** (argent réel, à l'usage) et les **« crédits »** internes de l'app (plafond de répartition entre agents) sont deux choses distinctes — les crédits contrôlent la dépense, ils ne la paient pas.

## Périmètre (YAGNI)

- **Inclus** : conversations multi-tours persistées par agent, réponses en streaming, deux niveaux (gratuit/premium), analyse de fichiers (premium), recherche web (premium), ancrage documentaire RAG sur la bibliothèque (premium), comptage des crédits + quota mensuel, écran admin (drapeau, pool, réallocation, usage).
- **Exclu** (plus tard) : partage de conversations entre agents, mémoire persistante inter-conversations, génération d'images, appels vocaux, fine-tuning, self-hébergement du modèle.
- **Confidentialité** : appels via l'API Anthropic ; l'organisation Anthropic est configurée en **Zero-Data-Retention** (aucune conservation). Réglage côté org, pas dans le code.

## Deux niveaux

| | **Gratuit** (sans drapeau) | **Premium** (drapeau super-admin) |
|---|---|---|
| Qui | Tout agent connecté + vérifié | Agents présélectionnés (`assistant_ia_actif`) |
| Modèle | `claude-haiku-4-5` | `claude-sonnet-5` (adaptive thinking) |
| Conversation | ✅ | ✅ |
| Analyse fichiers | ❌ | ✅ PDF, image, Excel, Word |
| Recherche web | ❌ | ✅ outil serveur `web_search` |
| Ancrage bibliothèque | ❌ | ✅ outil `rechercher_bibliotheque` + citations |
| Crédits | forfait fixe mensuel (config) | allocation mensuelle réallouable, puisée dans un pool global |

- Le module est **ouvert à tous** les agents connectés ; le drapeau ne barre pas l'accès, il fait passer du gratuit au premium.
- Détermination du niveau à l'exécution : `User::assistantEstPremium()` = `assistant_ia_actif || isAdmin()`.

## Crédits & réallocation

- Unité **crédit** = abstraction des jetons. Conversion interne (config `assistant.jetons_par_credit`, ex. 1 crédit = 1000 jetons). L'admin raisonne en crédits.
- **Pool global mensuel** premium : paramètre `assistant_pool_credits` (réglable par l'admin). L'admin **répartit** ce pool entre les agents premium via `users.assistant_quota_credits`. La somme des allocations ≤ pool ; l'écran admin affiche « alloué / pool » et avertit en cas de dépassement.
- **Réallouer** = ajuster les allocations individuelles (déplacer des crédits d'un agent à un autre) depuis l'écran admin.
- **Gratuit** : forfait fixe `assistant_forfait_gratuit` (config), séparé, ne pioche pas dans le pool premium.
- **Consommation** : après chaque réponse, crédits = ⌈(jetons_input + jetons_output) / jetons_par_credit⌉, calculés depuis `response.usage`. Agrégés par mois dans `assistant_usage`.
- **Quota** : avant chaque appel, vérifier `crédits_restants_du_mois = allocation − consommé_ce_mois > 0`. Sinon message poli « crédits épuisés ce mois » + invitation à demander un rechargement à l'admin. Réinitialisation mensuelle par clé `annee_mois`.

## Modèle de données

**`conversations`** — `id`, `user_id` → users, `titre` (auto : 1re question tronquée, ou résumé Claude), `modele` (string), `niveau` enum(`gratuit`,`premium`) figé à la création, `archivee_le` (nullable), timestamps.

**`messages`** — `id`, `conversation_id` → conversations (cascade), `role` enum(`user`,`assistant`), `contenu` (JSON — blocs texte / citations / appels d'outils / réfs fichiers), `jetons_input` (int, nullable), `jetons_output` (int, nullable), `credits` (int, nullable), timestamps.

**`pieces_jointes`** — `id`, `conversation_id` → conversations (cascade), `chemin` (disque privé), `nom_original`, `type_mime`, `taille` (int), `texte_extrait` (longtext, nullable — pour Excel/Word), timestamps.

**`assistant_usage`** — `id`, `user_id` → users, `annee_mois` (char(7), `YYYY-MM`), `credits_consommes` (int, défaut 0), `jetons_input` (int), `jetons_output` (int), timestamps. Unique(`user_id`,`annee_mois`).

**`users`** (colonnes ajoutées) — `assistant_ia_actif` (bool, défaut false), `assistant_quota_credits` (int, défaut 0 — allocation premium mensuelle).

**Paramètres globaux** — `assistant_pool_credits` et `assistant_forfait_gratuit` : ligne de réglages admin (table `parametres` si présente, sinon config + override en base). Éditable par le super-admin.

**Modèles Eloquent** : `Conversation` (belongsTo user, hasMany messages, hasMany piecesJointes), `Message` (belongsTo conversation), `PieceJointe` (belongsTo conversation), `AssistantUsage`. Helpers : `User::assistantEstPremium()`, `User::creditsRestants()` (allocation/forfait − consommé du mois).

## Accès (routes)

- **Utilisation** — tout compte connecté+vérifié, middleware `['auth','verified','password.change']` :
  - `/assistant` — `Assistant\Index` (liste des conversations + fil de discussion + saisie + streaming).
- **Administration** — super-admin, middleware `['auth','verified','password.change','role:admin']` (bypass EnsureRole pour `admin`) :
  - `/admin/assistant` — `Admin\Assistant\Gestion` : basculer `assistant_ia_actif` par agent, régler le pool et le forfait gratuit, répartir/réallouer les allocations, voir l'usage (crédits consommés par agent/mois).
- **Navigation** : lien « Assistant IA » visible par **tous** dans la barre latérale. Lien « Gérer l'assistant » visible pour l'admin.

## Intégration Claude (`App\Services\AssistantClaude`)

- **SDK PHP officiel Anthropic** (composer). Jamais de HTTP brut ni de shim d'un autre fournisseur.
- **Streaming** systématique ; rendu incrémental via Livewire `$this->stream(to: 'reponse', content: $chunk)`.
- **Modèle** selon le niveau : Sonnet 5 (premium, `thinking: adaptive`), Haiku 4.5 (gratuit, sans thinking).
- **Prompt système** : rôle d'assistant de la DGE, réponses en français, cadre professionnel/administratif, cite ses sources quand l'ancrage est utilisé, ne pas inventer de références juridiques.
- **Historique** : reconstruit le tableau `messages` de l'API depuis la conversation persistée à chaque tour.
- **Outils (premium uniquement)** :
  - `web_search` (outil serveur Anthropic).
  - `rechercher_bibliotheque(requete)` (outil personnalisé, boucle agentique) → exécute une recherche `FULLTEXT` sur `document_chunks` (Module A), renvoie les meilleurs passages + liens des documents ; Claude répond en citant. Voir dépendance ci-dessous.
- **Fichiers (premium)** :
  - PDF → bloc `document` (base64 ou Files API) ; images → bloc `image` (vision native).
  - Excel/Word → texte extrait côté serveur (PhpSpreadsheet / PhpWord) stocké dans `pieces_jointes.texte_extrait`, injecté comme bloc texte.
  - Upload `WithFileUploads`, disque **privé**, mimes autorisés (pdf, png, jpg, xlsx, docx), taille max (config, ex. 20 Mo).
- **Comptage** : lire `response.usage` (input + output, y c. cache) → crédits ; enregistrer sur le `Message` et incrémenter `assistant_usage`.

## Dépendance — Module Bibliothèque (ancrage RAG)

L'ancrage a besoin du **texte** des documents, pas seulement des PDF. Le Module A doit donc, à l'ingestion d'un document `source=fichier` :
- extraire le texte du PDF (pdftotext / Smalot\PdfParser) ;
- le découper en **chunks** (~500–800 mots, chevauchement léger) ;
- table **`document_chunks`** : `id`, `document_id` → documents (cascade), `ordre` (int), `contenu` (longtext), index **FULLTEXT** sur `contenu`. Réindexation à la mise à jour/suppression du document.
- L'outil `rechercher_bibliotheque` interroge cette table (seuls documents `actif=true`), renvoie titre + passage + lien fiche.

*(Ajout à intégrer au spec 2026-08-05 : nouvelle table `document_chunks` + étape d'extraction/chunking dans la gestion des documents.)*

## UI (façon claude.ai — skill ui-ux-pro-max, charte DGB)

- Disposition type messagerie IA : **colonne latérale** (bouton « Nouvelle conversation », liste des conversations récentes, archivées), **fil central** (bulles user/assistant, rendu Markdown, streaming), **zone de saisie** en bas (multi-lignes, bouton joindre un fichier — premium, bouton envoyer).
- **Jauge de crédits** (restants ce mois) + badge niveau (Gratuit / Premium) en tête.
- **Citations** rendues comme liens cliquables vers les fiches de la bibliothèque.
- Fonctions premium masquées/inertes en gratuit (joindre, indicateur web/ancrage) avec incitation discrète (« Demandez l'accès premium à l'admin »).
- États vides pédagogiques (exemples de prompts adaptés aux tâches DGE). Charte DGB (vert `#309966` / navy `#193761` / Open Sans), cartes `.card`, boutons `.btn`, accessibilité AA.

## Composants Livewire

- `Assistant\Index` — conversations + fil + saisie + envoi/streaming + upload (premium).
- `Admin\Assistant\Gestion` — drapeau premium par agent, pool + forfait, répartition/réallocation des crédits, tableau d'usage.

## Tests (Pest) — API Claude simulée (jamais d'appel réel)

- **Accès** : `/assistant` ouvert à un agent connecté ; `/admin/assistant` → 403 agent, OK admin.
- **Niveaux** : sans drapeau → modèle Haiku, conversation seule, fichiers/web/ancrage bloqués ; avec drapeau → Sonnet, capacités complètes.
- **Crédits** : consommation décrémente le restant ; quota atteint → appel bloqué + message ; réinit par `annee_mois`.
- **Réallocation** : l'admin ajuste les allocations ; la somme > pool est refusée/avertie ; forfait gratuit indépendant du pool.
- **Ancrage** : `rechercher_bibliotheque` ne renvoie que des chunks de documents actifs ; citations présentes dans la réponse.
- **Fichiers** : upload PDF/image accepté (`Storage::fake`) ; Excel/Word → `texte_extrait` renseigné ; mimes/tailles hors limites refusés.
- **Persistance** : conversation + messages enregistrés ; historique reconstruit correctement.

## Décomposition en tasks (indicatif pour le plan)

1. **Données + modèles** : migrations (conversations, messages, pieces_jointes, assistant_usage), colonnes users, réglages pool/forfait ; modèles + helpers (`assistantEstPremium`, `creditsRestants`).
2. **Service + chat** : `AssistantClaude` (SDK, streaming, prompt système, modèle par niveau) + `Assistant\Index` conversation persistée — chat texte gratuit + premium **sans outils** d'abord + route + nav.
3. **Crédits + admin** : comptage jetons→crédits, quota mensuel, blocage ; `Admin\Assistant\Gestion` (drapeau, pool, allocations, réallocation, usage) + route + nav.
4. **Capacités premium** : fichiers (PDF/image natif, Office extraction), `web_search`, outil `rechercher_bibliotheque` (dépend de `document_chunks` du Module A).
5. **Passe design ui-ux-pro-max** : interface chat, jauge crédits, citations, états vides + polish.
