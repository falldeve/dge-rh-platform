# Module Courrier — Design (v1)

**Date:** 2026-07-13
**Statut:** validé pour implémentation
**Rattachement:** sous-système de la plateforme DGE, distinct du module RH (réutilise agents, rôles, layout console). Branche de base : `feat/plan2-auth`.

## 1. Contexte et objectif

La DGE gère son courrier « à l'arrivée » via une **Fiche de Ventilation** papier (Bureau
du Courrier). Le DG impute le courrier en cochant, sur cette fiche :
- des **destinataires** (directions / services / bureaux),
- des **mentions** (« Soit transmis » : Pour attribution, Pour information, etc.).

Le module numérise ce processus : l'agent du bureau courrier enregistre le courrier,
**scanne** le document, **recopie l'imputation du DG** (les cases cochées), ce qui route
le courrier aux entités concernées et l'**archive**. Un directeur destinataire peut
**ré-imputer** (cascade) vers ses **divisions** ; son secrétaire recopie et route.

Le DG et les directeurs **imputent sur papier** ; l'application ne fait que **recopier**
(agent courrier au niveau DG, secrétaire au niveau direction) + conserver le scan.

## 2. Périmètre v1

Inclus :
- Enregistrement du courrier **arrivée** + scan.
- **Fiche de ventilation** niveau DG (recopiée par l'agent courrier) → routage aux entités.
- **Cascade** direction → divisions (recopiée par le secrétaire de la direction).
- **Consultation** par le service destinataire (chef + secrétaire de l'entité).
- **Registre** complet + recherche (bureau courrier).
- **Archives** : consultation/recherche de tout l'historique (rôle archiviste).
- **PDF** : génération de la Fiche de Ventilation (reproduction du modèle papier).
- Rôles : `courrier`, `archiviste` (nouveaux) ; `secretaire` (existant, étendu à la cascade).

Hors périmètre v1 :
- Courrier **départ** (sortant).
- Numérotation automatique (le numéro est saisi depuis le registre papier).
- Notifications email/SMS ; workflow de réponse ; gestion documentaire avancée (dossiers).

## 3. Stack

Laravel 12 + Livewire 3 + MySQL + Pest + Tailwind + barryvdh/laravel-dompdf (PDF).
Réutilise : `Agent`, `User` (rôles), middleware `role`, layout `components.layouts.rh`
(console à sidebar adaptative par rôle), stockage disque `public` (scans), accesseur
d'URL relative (comme les photos).

## 4. Entités destinataires (`entites`)

Unités routables du courrier, **paramétrables** (gérées par l'Admin RH), **distinctes**
des `directions` RH (car la fiche inclut des services hors directions).

| champ | type | notes |
|-------|------|-------|
| id | pk | |
| code | string unique | ex. `DOE`, `SP`, `BDA`, `DOE-CARTE` |
| nom | string | libellé complet |
| type | enum | `direction` \| `service` \| `division` |
| parent_id | fk entites nullable | une `division` pointe vers sa direction |
| chef_agent_id | fk agents nullable | responsable (pour l'accès en consultation) |
| secretaire_agent_id | fk agents nullable | secrétaire de l'entité (accès + cascade) |
| actif | bool | défaut true |
| timestamps | | |

**Seed initial** (d'après la fiche + personnel) :
- `direction` : DG, DOE, DFC, DRHF, SI.
- `service` : Secrétariat Particulier (SP), Bureau Documentation et Archives (BDA),
  Bureau Coopération internationale (BCI), Bureau Courrier (BC).
- `division` (rattachées, exemples issus des fonctions des agents) : DOE → Carte
  électorale et fichiers, Études et Affaires juridiques, Suivi Opérations et Missions,
  Logistique ; DFC → Formation permanente, Relations publiques et Communication ; etc.
  L'Admin RH complète/ajuste via l'écran de gestion.

**Chef / secrétaire d'une entité** : renseignés à la gestion. Pour les directions, on peut
initialiser `chef_agent_id` depuis `directions.chef_id` correspondant.

## 5. Mentions (« Soit transmis »)

Liste **fixe** (constante `Imputation::MENTIONS`), stockée en JSON sur l'imputation :

`urgent`, `men_parler`, `etude_reponse`, `accord`, `information`, `attribution`,
`exploitation`, `execution`, `suite_a_donner`, `a_suivre`, `diffusion`, `a_classer`
(libellés : Urgent, M'en parler, Pour étude et réponse, Accord, Pour information,
Pour attribution, Pour exploitation, Pour exécution, Pour suite à donner, À suivre,
Pour diffusion, À classer).

## 6. Données

### `courriers`
| champ | type | notes |
|-------|------|-------|
| id | pk | |
| numero | string unique | saisi depuis le registre papier (ex. `000145`) |
| objet | string | requis |
| expediteur | string | requis (émetteur du courrier) |
| date_arrivee | date | requis |
| date_depart | date nullable | date de sortie/ventilation |
| scan_path | string nullable | document scanné (disque public) |
| enregistre_par | fk users | agent du bureau courrier |
| timestamps | | |

### `imputations` (une fiche de ventilation = une imputation, à un niveau)
| champ | type | notes |
|-------|------|-------|
| id | pk | |
| courrier_id | fk courriers cascadeOnDelete | |
| niveau | enum | `dg` (ventilation initiale) \| `direction` (cascade) |
| entite_source_id | fk entites nullable | la direction qui cascade (null au niveau dg) |
| mentions | json | codes de mentions cochées |
| observations | text nullable | |
| signataire_nom | string nullable | qui a signé la fiche papier (DG ou directeur) |
| scan_path | string nullable | scan de la fiche (optionnel) |
| saisi_par | fk users | agent courrier (dg) ou secrétaire (direction) |
| timestamps | | |

### pivot `entite_imputation`
`imputation_id`, `entite_id` = destinataires cochés de cette fiche.

Modèles : `Entite` (parent/enfants, chef, secretaire), `Courrier` (imputations, enregistrePar),
`Imputation` (courrier, entiteSource, destinataires[], saisiPar).

## 7. Rôles & accès

| Rôle | Capacités courrier |
|------|--------------------|
| **courrier** (nouveau) | Enregistrer un courrier + scan ; saisir la ventilation DG (destinataires + mentions) ; registre complet + recherche ; imprimer la fiche PDF |
| **secrétaire** (existant) | Voir les courriers imputés à **sa** direction ; créer la cascade vers les divisions (destinataires + mentions + scan) |
| **archiviste** (nouveau) | Consulter/rechercher **tout** l'historique (lecture seule) |
| **chef d'entité** (directeur, via son rôle) | Voir les courriers imputés à son entité |

**Règle de consultation d'une entité** : un utilisateur voit les courriers d'une entité si
son agent est `chef_agent_id` **ou** `secretaire_agent_id` de cette entité ; les rôles
`courrier` et `archiviste` voient tout. Implémenté via une **policy** / requête dédiée.

Enum `users.role` étendu : `agent`, `chef_direction`, `admin_rh`, `dg`, `secretaire`,
`courrier`, `archiviste` (migration ALTER, comme l'ajout de `secretaire`).

## 8. Workflow

1. Courrier arrive → **agent courrier** l'enregistre (`numero`, `objet`, `expediteur`,
   dates) + **scanne** (`scan_path`).
2. Agent courrier crée la **fiche de ventilation niveau `dg`** : coche les entités
   destinataires + les mentions (recopie du papier) + observations + `signataire_nom` = DG.
3. Le courrier apparaît chez chaque **entité destinataire** (chef + secrétaire).
4. Un **directeur** destinataire décide de cascader → son **secrétaire** crée une
   imputation **niveau `direction`** (`entite_source` = la direction) vers les **divisions**
   concernées + mentions + scan éventuel.
5. **Archivage** : le courrier + toutes ses imputations restent consultables (bureau
   courrier, archiviste, entités concernées). Aucune action séparée — l'archive EST le
   registre historisé.

## 9. Fiche de Ventilation PDF

Contrôleur + vue Blade reproduisant le modèle papier (en-tête ministériel, N°, dates,
liste des destinataires avec cases cochées, « Soit transmis » avec mentions cochées,
observations, signataire). Générée depuis une imputation. Accès : agent courrier /
archiviste / entités concernées.

## 10. Écrans (Livewire, layout console)

- **Bureau courrier** : registre (liste + recherche par n°/objet/expéditeur/date),
  « Nouveau courrier » (enregistrement + scan), fiche courrier (détail + ventilation DG +
  historique + impression PDF).
- **Secrétaire** : « Courriers de ma direction » (imputés à sa direction) + action
  « Cascader » vers les divisions.
- **Chef d'entité** : « Mes courriers » (imputés à son entité), lecture + PDF.
- **Archiviste** : recherche/consultation globale (lecture seule).
- **Admin RH** : gestion des entités (CRUD, chef/secrétaire, divisions).

## 11. Découpage en plans d'implémentation

1. **Fondations courrier** : rôles `courrier`/`archiviste` (enum) ; table + modèle `entites`
   + seeder (directions/services/divisions) ; écran Admin RH de gestion des entités.
2. **Enregistrement + ventilation DG** : `courriers` + `imputations` + pivot ; UI bureau
   courrier (enregistrer + scan, ventiler, registre + recherche).
3. **Cascade + consultation service** : imputation niveau `direction` (secrétaire) vers
   divisions ; écrans de consultation par entité (chef + secrétaire) ; policy d'accès.
4. **Archives + PDF** : rôle archiviste (recherche globale) + génération PDF de la Fiche
   de Ventilation.

Chaque plan produit un logiciel testable autonome.

## 12. Tests

Feature tests Pest par module :
- Entités : seed, hiérarchie division→direction, CRUD, chef/secrétaire.
- Enregistrement courrier + scan ; unicité `numero`.
- Ventilation DG : mentions + destinataires ; routage.
- Cascade : imputation direction→divisions par le secrétaire ; autorisations.
- Consultation : chef/secrétaire d'entité voient leurs courriers ; autres 403 ; archiviste/courrier voient tout.
- PDF fiche de ventilation (200 + application/pdf ; contenu résolu).
