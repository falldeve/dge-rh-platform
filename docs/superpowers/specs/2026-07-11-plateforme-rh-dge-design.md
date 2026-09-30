# Plateforme RH DGE — Design (v1)

**Date:** 2026-07-11
**Statut:** validé pour implémentation. Modèles de documents reçus (voir §11 + `docs/references/modeles/`). Rôle `secretaire` ajouté pour les ordres de mission (Plan 5).

## 1. Contexte et objectif

La Direction Générale des Élections (DGE, Sénégal) veut une plateforme web de
gestion des ressources humaines. Chaque agent dispose d'un compte pour soumettre
des demandes (congé, permission, ordre de mission) qui suivent un circuit de
validation hiérarchique. L'administration RH (DRHF) pilote le personnel, les
soldes et les états.

Effectif de départ : **117 agents** importés depuis la liste officielle
« SITUATION PERSONNEL MAI 2026 » (`liste personnel DGE.docx`).

## 2. Périmètre v1

Inclus :
- Import du personnel + fiche agent enrichie.
- Auto-inscription des agents et gestion des rôles.
- Demandes : congé annuel, permission d'absence, ordre de mission.
- Circuit de validation à 2 niveaux (Chef de direction → DRHF).
- Notifications in-app.
- Gestion des directions (dont création de la direction SI).
- Exports / documents imprimables (état congés, ordre de mission, décision de congé).

Hors périmètre v1 (potentiel plus tard) :
- Congés exceptionnels (maternité, événements familiaux, maladie/certificat).
- Notifications email / SMS.
- Décompte automatique du reliquat de fin d'année (géré manuellement par la RH en v1).
- Gestion financière détaillée des frais de mission.

## 3. Stack technique

- **Laravel 12** (PHP), **Livewire 3**, **MySQL**, **Tailwind CSS**.
- Autorisation via **Policies** Laravel.
- Notifications via le système natif Laravel (table `notifications`, canal `database`).
- Tests : **Pest** (feature tests).
- Cohérent avec l'écosystème existant du client (OFNAC, HAAS, recensement).

## 4. Organisation (directions)

Directions issues de la liste : **DG** (26), **DOE** (69), **DRHF** (12), **DFC** (10).

Ajout : **SI — Service Informatique**, traité comme direction à part entière.
Les agents informatiques actuellement rattachés à DG (ex. Cheikh Tidiane Diallo —
Responsable Service informatique, Serigne Mbacké Niang — Agent service informatique)
sont réaffectés à SI.

Les directions sont paramétrables : la DRHF peut en créer/modifier et y affecter
des agents. Chaque direction a un chef (`chef_id`) qui valide le niveau 1.

## 5. Modèle de données

### `directions`
| champ | type | notes |
|-------|------|-------|
| id | pk | |
| code | string unique | DG, DOE, DRHF, DFC, SI |
| nom | string | libellé complet |
| chef_id | fk agents nullable | chef de direction (validateur N1) |

### `agents`
| champ | type | notes |
|-------|------|-------|
| id | pk | |
| prenoms | string | |
| noms | string | |
| matricule | string unique nullable | matricule de solde ou NIN ; peut manquer dans la liste |
| profession | string nullable | ex. « Adjudant de police », « Magistrat » |
| fonction | string nullable | ex. « Chef de bureau Statistique » |
| direction_id | fk directions | |
| statut | enum | fonctionnaire / police / contractuel_pav / autre |
| solde_conge_jours | decimal | solde de congé annuel, configurable par agent |
| telephone | string nullable | complété par la RH/l'agent |
| email | string nullable | |
| photo_path | string nullable | photo d'identité |
| date_naissance | date nullable | |
| date_prise_service | date nullable | ancienneté |
| user_id | fk users nullable | null tant que le compte n'est pas créé |

### `users`
Auth Laravel standard, relation 1-1 avec `agents`.
| champ | type | notes |
|-------|------|-------|
| id | pk | |
| name | string | |
| email | string unique nullable | peut servir d'identifiant |
| password | string | |
| role | enum | agent / chef_direction / admin_rh / dg |

Note : l'identifiant de connexion principal est le **matricule** (unique), l'email
étant optionnel. À caler à l'implémentation (login par matricule).

### `demandes`
| champ | type | notes |
|-------|------|-------|
| id | pk | |
| agent_id | fk agents | demandeur |
| type | enum | conge_annuel / permission / ordre_mission |
| date_debut | date | |
| date_fin | date | |
| nb_jours | decimal | calculé en jours calendaires |
| motif | text nullable | |
| statut | enum | brouillon / soumise / validee_chef / validee_rh / refusee |
| piece_jointe_path | string nullable | ex. justificatif mission |
| meta | json nullable | champs spécifiques mission (destination, objet, transport…) |

### `validations`
| champ | type | notes |
|-------|------|-------|
| id | pk | |
| demande_id | fk demandes | |
| validateur_id | fk agents | qui a statué |
| niveau | enum | chef / rh |
| decision | enum | ok / refus |
| commentaire | text nullable | |
| created_at | timestamp | date de décision |

### `notifications`
Table native Laravel (canal database). Notifie l'agent à chaque transition de statut.

## 6. Rôles et autorisations

| Rôle | Capacités |
|------|-----------|
| **Agent** | créer/soumettre ses demandes, consulter son solde et son historique, gérer une partie de sa fiche (contact, photo) |
| **Chef de direction** | tout ce qu'un agent fait + valider/refuser (niveau 1) les demandes des agents de **sa** direction |
| **Admin RH (DRHF)** | valider/refuser (niveau 2), CRUD agents, ajuster soldes, gérer directions, promouvoir des rôles, exports, accès global |
| **Secrétaire** | initier et imprimer les **ordres de mission** pour les agents de sa direction (pas de workflow de validation) ; sinon capacités d'agent |
| **DG** | tableaux de bord et états en **lecture seule** (synthèse par direction, taux d'absence) |

Contrôle via Policies : un chef ne voit/valide que sa propre direction. L'admin RH
a une portée globale. Le DG est restreint à la consultation.

## 7. Comptes et inscription

1. Import initial des 117 agents (commande Artisan lisant la liste) dans `agents`,
   sans `user_id`.
2. Auto-inscription : l'agent saisit **matricule + nom + prénom**. Si cela
   correspond à un agent existant **sans compte**, il définit son mot de passe et
   le compte est créé (rôle `agent` par défaut).
3. Les agents sans matricule dans la liste (ex. Adama Faye, Souleymane Sow) :
   la RH complète le matricule ou active le compte manuellement.
4. La DRHF promeut les chefs de direction, les admins RH et le DG.

Sécurité : l'auto-inscription exige la correspondance exacte matricule + nom +
prénom d'un agent pré-chargé non encore réclamé, ce qui empêche les inscriptions
arbitraires.

## 8. Workflow des demandes (2 niveaux)

```
Agent soumet
   → Chef de direction (niveau 1) : valide ou refuse
        refus → statut « refusee », agent notifié (motif)
        valide → statut « validee_chef »
   → Admin RH / DRHF (niveau 2) : valide ou refuse
        refus → statut « refusee », agent notifié (motif)
        valide → statut « validee_rh » (finale)
```

Règles :
- Refus à n'importe quel niveau stoppe le circuit et notifie l'agent avec le motif.
- **Demande émise par un chef de direction** : elle part directement à la DRHF
  (niveau 1 sauté, pas d'auto-validation).
- **Congé annuel** : le solde (`solde_conge_jours`) est décrémenté du `nb_jours`
  **uniquement à la validation RH finale** (`validee_rh`).
- **Permission** et **ordre de mission** : ne touchent pas le solde de congé.
- Chaque transition génère une notification in-app pour l'agent.

## 9. Calcul du congé

- `nb_jours` = nombre de **jours calendaires** entre `date_debut` et `date_fin`
  (inclus), week-ends et jours fériés compris.
- Congé annuel : `nb_jours` doit être ≤ `solde_conge_jours` au moment de la soumission.
- Reliquat de fin d'année : **géré manuellement** par la RH (ajustement des soldes
  en début d'année). Pas d'automatisme en v1.

## 10. Écrans

- **Agent** : tableau de bord (solde, demandes en cours), formulaire nouvelle
  demande, historique, fiche personnelle (contact/photo).
- **Chef de direction** : file d'attente de validation (sa direction), historique
  des décisions.
- **Admin RH (DRHF)** : validation N2, gestion des agents (CRUD + solde), gestion
  des directions, promotions de rôle, exports, tableau de bord global.
- **DG** : tableau de bord synthèse (par direction, taux d'absence) en lecture.

## 11. Modèles de documents (REÇUS)

Modèles officiels fournis par le client dans `docs/references/modeles/` (voir le
`README.md` du dossier pour le mapping des champs). **Signataire des deux documents :
le Directeur Général** ; son nom et sa fonction sont configurables (setting `dg_nom`,
`dg_fonction`).

- **Ordre de mission** (`Ordre de Mission DGE.docx`) — champs : prénom+nom, matricule,
  fonction, indice, groupe, destination (« se rendre à »), motif, date départ/retour,
  moyen de transport, imputation budgétaire, chapitre, article. **Initié par le
  secrétaire de la direction, imprimé directement, sans workflow de validation.**
  Champs spécifiques stockés dans `demandes.meta` ; statut `emise`.
- **Attestation de cessation de service (congé)** (`CESSATION DE SERVICE congé.docx`) —
  généré après validation DRHF finale (`validee_rh`) d'un congé annuel. Contient le
  nb de jours en toutes lettres et la date de reprise (`date_fin` + 1 jour).
- **État des congés** (Excel/PDF) — liste par période/direction avec soldes.

Décisions liées : l'**ordre de mission n'est PAS en libre-service** — un nouveau rôle
**`secretaire`** (rattaché à sa direction) l'initie. Implémentation : ordres de mission
+ rôle secrétaire = Plan 5 ; attestation congé + état congés = Plan 6.

## 12. Tests

Feature tests Pest, par module :
- Inscription (match matricule/nom/prénom, rejet des cas invalides, unicité).
- Workflow de validation (transitions, refus, cas du chef → DRHF direct).
- Décompte du solde de congé (décrément à `validee_rh`, contrôle solde suffisant).
- Policies d'accès (chef limité à sa direction, DG lecture seule, portée RH globale).
- Import du personnel (117 agents, réaffectation SI, agents sans matricule).

## 13. Ordre d'implémentation pressenti

1. Squelette Laravel + auth + migrations (directions, agents, users).
2. Import du personnel + réaffectation SI.
3. Auto-inscription + rôles + policies.
4. Fiches agents + gestion RH (CRUD, soldes, directions).
5. Demandes + workflow de validation + notifications in-app.
6. Décompte du solde de congé.
7. Tableaux de bord (agent, chef, RH, DG).
8. Exports / documents PDF (à réception des modèles).
