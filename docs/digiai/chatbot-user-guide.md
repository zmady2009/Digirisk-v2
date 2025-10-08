# Chatbot DigiAI – Guide utilisateur

## Accès

- Le chatbot est disponible sur l’interface publique des tickets (widget portant l’attribut `data-digiai-chatbot`).
- Le bouton d’envoi change d’état pendant l’analyse (message « Analyse... »).

## Parcours type

1. **Saisie de la question** : décrire un incident, un risque ou une demande d’assistance.
2. **Réception de la réponse IA** : DigiAI renvoie un message pédagogique, des recommandations d’actions et un résumé synthétique.
3. **Boucle de dialogue** : enchaîner les questions, DigiAI conserve l’historique de la conversation.
4. **Exploitation** : utiliser les recommandations pour compléter les champs du ticket ou lancer des plans d’action.

## Bonnes pratiques

- Fournir un maximum de contexte (localisation, service, gravité perçue) pour des réponses plus pertinentes.
- Vérifier les recommandations avant application et les rattacher à des plans d’action internes.
- En cas d’erreur technique, relancer la question ; si le problème persiste, contacter l’administrateur (consulter les journaux `digiai-*.log`).
