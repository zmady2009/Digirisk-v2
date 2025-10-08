# DigiAI Gateway – API développeur

## Endpoints

### `view/digiriskelement/backend_endpoint_for_chatgpt.php`

- **Méthode** : `POST`
- **Paramètres** :
  - `action` (`analyze_text` | `analyze_image`)
  - `analysis_text` (texte libre, requis si `action=analyze_text`)
  - `image_file` (fichier image, requis si `action=analyze_image`)
- **Réponse** :
  ```json
  {
    "success": true,
    "data": {
      "risks": [
        {
          "title": "machine_PictoCategorie_v2",
          "description": "...",
          "cotation": 70,
          "actions": ["Action 1", "Action 2"]
        }
      ],
      "recommendations": ["..."],
      "summaries": ["..."],
      "metadata": {
        "confidence": 82,
        "label": "Analyse poste maintenance"
      }
    }
  }
  ```

### `core/ajax/digiai_chat.php`

- **Méthode** : `POST` (JSON)
- **Payload** :
  ```json
  {
    "messages": [
      {"role": "user", "content": "Quel est le risque ?"},
      {"role": "assistant", "content": "..."}
    ],
    "context": {
      "summary": "Ticket #42 – Entrepôt logistique"
    }
  }
  ```
- **Réponse** :
  ```json
  {
    "success": true,
    "data": {
      "messages": ["Réponse détaillée..."],
      "recommendations": ["Former l'équipe", "Mettre à jour le plan d'action"],
      "summaries": ["Risque critique manutention"],
      "metadata": {
        "confidence": 78
      }
    }
  }
  ```

## Objets principaux

### `class/digiai_gateway.class.php`

- `run(array $messages, array $options = [])`
  - `messages` : payload complet pour l'API Chat.
  - `options` : `model`, `temperature`, `max_tokens`, `purpose`, `schema_description`.
  - Retourne la réponse validée.

- Validation JSON :
  - `purpose=risk` (par défaut) exige la clé `risks`.
  - `purpose=chatbot` exige la clé `messages`.
  - Les recommandations, résumés et métadonnées sont toujours normalisés.

## Hooks d'intégration

- Les journaux sont disponibles dans `DOL_DATA_ROOT/digiai/logs/*.log`.
- Les contrôleurs front (`js/modules/digiai.js`, `js/modules/digiai.chatbot.js`) publient des événements DOM (`digiai-history-entry`) facilement interceptables pour déclencher des workflows.
- Le cache mémoire (TTL par défaut : 30s) peut être ajusté via `DIGIRISKDOLIBARR_DIGIAI_CACHE_TTL`.
