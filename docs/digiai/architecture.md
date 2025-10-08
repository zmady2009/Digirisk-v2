# DigiAI – Architecture cible

## Vue d'ensemble

La nouvelle intégration DigiAI repose sur trois couches principales :

1. **Orchestration IA** : assurée par `DigiaiGateway`, responsable de la gouvernance des appels OpenAI (validation JSON, re-prompt, cache mémoire, journalisation et reprise sur erreur).
2. **Couche métier** : exploitation des données DigiRisk (risques, évaluations, incidents, plans d'action) via les écrans existants et les nouveaux contrôleurs JavaScript.
3. **Couche interaction** : onglet DigiAI enrichi, chatbot public et tableaux de bord capables d'exposer les métriques IA.

## Flux majeurs

1. **Analyse texte/image**
   - Le front (`js/modules/digiai.js`) collecte le texte ou l'image et appelle `backend_endpoint_for_chatgpt.php`.
   - Le backend construit le prompt, invoque `DigiaiGateway` qui sécurise l'appel OpenAI, valide la réponse et renvoie un JSON structuré (`risks`, `recommendations`, `summaries`, `metadata`).
   - Le front restitue les risques, alimente l'historique local et affiche les métadonnées (confiance, recommandations, résumés).

2. **Chatbot public**
   - Le composant `DigiAIChatbot` expédie les messages utilisateur vers `core/ajax/digiai_chat.php`.
   - Le service `DigiaiGateway` orchestre l'appel IA avec un prompt conversationnel dédié.
   - Les messages, recommandations et résumés sont renvoyés au front pour enrichir la discussion et préconiser des actions.

3. **Journalisation et supervision**
   - Chaque interaction est loggée dans `DOL_DATA_ROOT/digiai/logs/` pour audit et pilotage (statut, latence, modèle, erreurs de schéma).

## Sécurité & gouvernance

- Revalidation TLS systématique, gestion des clés OpenAI depuis la configuration Dolibarr, absence de secrets en dur.
- Re-prompt automatique lorsque le schéma JSON n'est pas respecté.
- Cache mémoire à durée courte pour limiter les appels redondants et sécuriser la scalabilité.
- Hooks prêts pour l'extension dashboards (latence, ratio de succès, coûts).

## Extensibilité

- Le service `DigiaiGateway` accepte un paramétrage dynamique (`model`, `temperature`, `schema_description`).
- Les contrôleurs front peuvent être enrichis pour pousser les résultats vers d'autres objets métiers (incidents, tickets, document unique).
- Les journaux peuvent alimenter un pipeline BI/monitoring externe.
