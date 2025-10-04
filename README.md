# DigiriskDolibarr sur [DOLIBARR ERP CRM](https://dolibarr.org)

## Informations

- Numéro du module : 436302
- Dernière mise à jour : 29/08/2025
- Éditeur : [Evarisk](https://evarisk.com)
- Thème : Eldy Menu
- Licence : GPLv3
- Disponible sur : Windows - MacOS - Linux

### Version

- Version : 21.1.0
- PHP : 7.4.33
- Compatibilité : Dolibarr 20.0.0 - 21.0.1
- Saturne Framework : 21.0.0

## Liens

- Support & Assistance : [Forum dolibarr.fr](https://dolibarr.fr) / Par mail à technique@evarisk.com
- Demo : [Demo Digirisk](https://demodoli.digirisk.com) - ID: demo - Password: demo
- Documentation : [Wiki Digirisk](https://wiki.dolibarr.org/index.php/Module_DigiriskDolibarr)
- Projet GitHub : [Projet Digirisk](https://github.com/Evarisk/Digirisk/projects?query=is%3Aopen)
- Saturne Framework : [Télécharger Saturne](https://dolistore.com/fr/modules/1906-Saturne.html)
- Forum : [Forum Digirisk](https://dolibarr.fr/forum/t/module-digirisk-document-unique/37119)
- D'autres modules développés par Evarisk disponibles sur [Dolistore.com](https://dolistore.com)
- Le récapitulatif avec tous les liens [Linktree](https://linktr.ee/DigiRisk)

## Fonctionnalités

Gérez les risques de votre entreprise et créez votre Document Unique en toute simplicité

## Traduction

- Français
- Anglais

## Installation

### Méthode 1 :

- Depuis le menu "Déployer/Installer un module externe" de Dolibarr :
- Glisser l'archive ZIP 'module_digiriskdolibarr-X.Y.Z' et cliquer sur "ENVOYER FICHIER"
- Activer le module dans la liste des Modules/Applications installés

### Méthode 2 :

- Dans le dossier "dolibarr/htdocs/custom" copier la ligne suivante :
```
git clone -b main https://github.com/Evarisk/Digirisk.git digiriskdolibarr
git clone -b main https://github.com/Evarisk/Saturne.git saturne
```
- Activer le module dans la liste des Modules/Applications installés

## Webhook n8n pour les tickets publics

Activez le webhook depuis **Digirisk ▸ Tickets ▸ Interface publique** puis saisissez l'URL fournie par n8n (ex. `https://n8n.example/webhook/hse-report`). Un secret optionnel permet de générer l'en-tête `X-Digirisk-Signature` (HMAC SHA-256) afin de vérifier l'intégrité du payload côté n8n. Un exemple de workflow est disponible dans `docs/n8n/digirisk-hse-report.json`.

Pour tester manuellement :

```bash
php -r 'echo "sha256=" . hash_hmac("sha256", file_get_contents("payload.json"), "mon-secret");'
curl -X POST https://n8n.example/webhook/hse-report \
  -H "Content-Type: application/json" \
  -H "X-Digirisk-Signature: sha256=..." \
  --data @payload.json
```
