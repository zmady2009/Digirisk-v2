(function(window, document) {
  'use strict';

  function resolveEndpoint(root) {
    const explicit = root.getAttribute('data-digiai-endpoint');
    if (explicit) {
      return explicit;
    }

    const dolUrlRootInput = document.getElementById('dol_url_root');
    const dolUrlRoot = dolUrlRootInput ? dolUrlRootInput.value : '';
    if (dolUrlRoot) {
      return dolUrlRoot + '/custom/digiriskdolibarr/core/ajax/digiai_chat.php';
    }

    return 'core/ajax/digiai_chat.php';
  }

  class DigiAIChatbot {
    constructor(root) {
      this.root = root;
      this.endpoint = resolveEndpoint(root);
      this.messages = [];
      this.context = {};
      this.texts = {
        title: root.getAttribute('data-title') || 'Assistant DigiAI',
        subtitle: root.getAttribute('data-subtitle') || 'Votre copilote prévention en temps réel.',
        recommendationsLabel: root.getAttribute('data-recommendations-label') || 'Recommandations clés',
        recommendationsEmpty: root.getAttribute('data-recommendations-empty') || 'Aucune recommandation disponible.',
        summariesLabel: root.getAttribute('data-summaries-label') || 'Synthèse automatique',
        summariesEmpty: root.getAttribute('data-summaries-empty') || 'Pas encore de synthèse générée.',
        confidenceLabel: root.getAttribute('data-confidence-label') || 'Indice de confiance',
        confidenceEmpty: root.getAttribute('data-confidence-empty') || '--'
      };
    }

    init() {
      this.render();
      this.bindEvents();
    }

    render() {
      this.root.classList.add('digiai-chatbot');

      const header = document.createElement('div');
      header.className = 'digiai-chatbot__header';

      const avatar = document.createElement('div');
      avatar.className = 'digiai-chatbot__avatar';
      avatar.innerHTML = '<i class="fas fa-robot" aria-hidden="true"></i>';

      const headerContent = document.createElement('div');
      const title = document.createElement('h4');
      title.className = 'digiai-chatbot__title';
      title.textContent = this.texts.title;
      const subtitle = document.createElement('p');
      subtitle.className = 'digiai-chatbot__subtitle';
      subtitle.textContent = this.texts.subtitle;
      headerContent.appendChild(title);
      headerContent.appendChild(subtitle);

      header.appendChild(avatar);
      header.appendChild(headerContent);

      this.messageList = document.createElement('div');
      this.messageList.className = 'digiai-chatbot__messages';

      this.insights = document.createElement('div');
      this.insights.className = 'digiai-chatbot__insights';
      this.recommendationsCard = this.buildListInsight(this.texts.recommendationsLabel, this.texts.recommendationsEmpty);
      this.summariesCard = this.buildTextInsight(this.texts.summariesLabel, this.texts.summariesEmpty);
      this.confidenceCard = this.buildMetricInsight(this.texts.confidenceLabel, this.texts.confidenceEmpty);

      this.form = document.createElement('form');
      this.form.className = 'digiai-chatbot__form';

      this.textarea = document.createElement('textarea');
      this.textarea.placeholder = this.root.getAttribute('data-placeholder') || 'Posez votre question sur les risques...';
      this.textarea.required = true;

      const actions = document.createElement('div');
      actions.className = 'digiai-chatbot__actions';
      this.submitButton = document.createElement('button');
      this.submitButton.type = 'submit';
      this.submitButton.className = 'button small';
      this.submitButton.textContent = this.root.getAttribute('data-send-label') || 'Envoyer';

      actions.appendChild(this.submitButton);
      this.form.appendChild(this.textarea);
      this.form.appendChild(actions);

      this.root.appendChild(header);
      this.root.appendChild(this.messageList);
      this.insights.appendChild(this.recommendationsCard.card);
      this.insights.appendChild(this.summariesCard.card);
      this.insights.appendChild(this.confidenceCard.card);
      this.root.appendChild(this.insights);
      this.root.appendChild(this.form);
    }

    bindEvents() {
      this.form.addEventListener('submit', (event) => {
        event.preventDefault();
        const value = this.textarea.value.trim();
        if (!value) {
          return;
        }
        this.textarea.value = '';
        this.pushMessage('user', value);
        this.sendToServer();
      });
    }

    pushMessage(role, content) {
      this.messages.push({ role: role, content: content });
      this.renderMessage(role, content);
      this.scrollToBottom();
    }

    renderMessage(role, content) {
      const wrapper = document.createElement('div');
      wrapper.className = 'digiai-chatbot__message digiai-chatbot__message--' + role;
      const bubble = document.createElement('div');
      bubble.className = 'digiai-chatbot__bubble';
      bubble.appendChild(this.createContentFragment(content));
      wrapper.appendChild(bubble);
      this.messageList.appendChild(wrapper);
    }

    scrollToBottom() {
      this.messageList.scrollTop = this.messageList.scrollHeight;
    }

    async sendToServer() {
      this.setLoading(true);
      try {
        const token = window.saturne && window.saturne.toolbox && window.saturne.toolbox.getToken ? window.saturne.toolbox.getToken() : '';
        const encodedToken = encodeURIComponent(token || '');
        const url = this.endpoint + (this.endpoint.indexOf('?') === -1 ? '?token=' + encodedToken : '&token=' + encodedToken);

        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify({
            messages: this.messages,
            context: this.context
          })
        });

        if (!response || typeof response.ok === 'undefined') {
          throw new Error('Réponse réseau DigiAI invalide');
        }

        let result;
        try {
          result = await response.json();
        } catch (parseError) {
          throw new Error('Réponse DigiAI illisible');
        }

        if (!response.ok || !result || !result.success) {
          throw new Error(result.error || 'Erreur DigiAI');
        }

        const assistantMessages = Array.isArray(result.data.messages) ? result.data.messages : [];
        assistantMessages.forEach((message) => {
          this.pushMessage('assistant', message);
        });

        if (result.data && typeof result.data.context === 'object') {
          this.context = result.data.context;
        }

        this.updateInsights(result.data || {});

      } catch (error) {
        console.error('DigiAI chatbot error', error);
        this.pushMessage('assistant', error.message);
      } finally {
        this.setLoading(false);
      }
    }

    setLoading(isLoading) {
      if (isLoading) {
        this.submitButton.disabled = true;
        this.submitButton.textContent = this.root.getAttribute('data-loading-label') || 'Analyse...';
      } else {
        this.submitButton.disabled = false;
        this.submitButton.textContent = this.root.getAttribute('data-send-label') || 'Envoyer';
      }
    }

    buildListInsight(label, emptyText) {
      const card = document.createElement('div');
      card.className = 'digiai-chatbot__card';
      const title = document.createElement('h5');
      title.textContent = label;
      const list = document.createElement('ul');
      list.className = 'digiai-list';
      const empty = document.createElement('p');
      empty.className = 'digiai-empty-state';
      empty.textContent = emptyText;
      card.appendChild(title);
      card.appendChild(list);
      card.appendChild(empty);
      list.style.display = 'none';
      return { card: card, list: list, empty: empty, emptyText: emptyText };
    }

    buildTextInsight(label, emptyText) {
      const card = document.createElement('div');
      card.className = 'digiai-chatbot__card';
      const title = document.createElement('h5');
      title.textContent = label;
      const paragraph = document.createElement('p');
      paragraph.className = 'digiai-empty-state';
      paragraph.textContent = emptyText;
      card.appendChild(title);
      card.appendChild(paragraph);
      return { card: card, text: paragraph, emptyText: emptyText };
    }

    buildMetricInsight(label, emptyText) {
      const card = document.createElement('div');
      card.className = 'digiai-chatbot__card';
      const title = document.createElement('h5');
      title.textContent = label;
      const metric = document.createElement('div');
      metric.className = 'digiai-chatbot__metrics';
      metric.textContent = emptyText;
      card.appendChild(title);
      card.appendChild(metric);
      return { card: card, metric: metric, emptyText: emptyText };
    }

    updateInsights(data) {
      const recommendations = Array.isArray(data.recommendations) ? data.recommendations : [];
      if (recommendations.length > 0) {
        this.recommendationsCard.list.innerHTML = '';
        recommendations.forEach((item) => {
          const li = document.createElement('li');
          li.textContent = item;
          this.recommendationsCard.list.appendChild(li);
        });
        this.recommendationsCard.list.style.display = 'block';
        this.recommendationsCard.empty.style.display = 'none';
      } else {
        this.recommendationsCard.list.style.display = 'none';
        this.recommendationsCard.empty.style.display = 'block';
        this.recommendationsCard.empty.textContent = this.recommendationsCard.emptyText;
      }

      const summaries = Array.isArray(data.summaries) ? data.summaries : [];
      this.summariesCard.text.className = summaries.length > 0 ? '' : 'digiai-empty-state';
      this.summariesCard.text.textContent = summaries.length > 0 ? summaries.join(' • ') : this.summariesCard.emptyText;

      const confidence = data.metadata && typeof data.metadata.confidence !== 'undefined' ? data.metadata.confidence : '';
      this.confidenceCard.metric.textContent = confidence !== '' ? confidence + '%' : this.confidenceCard.emptyText;
    }

    createContentFragment(content) {
      const fragment = document.createDocumentFragment();
      const raw = typeof content === 'string' ? content : '';
      if (!raw) {
        fragment.appendChild(document.createTextNode(''));
        return fragment;
      }

      const blocks = raw.split(/\n{2,}/).filter((block) => block.trim().length > 0);
      if (blocks.length === 0) {
        fragment.appendChild(document.createTextNode(raw));
        return fragment;
      }

      blocks.forEach((block) => {
        const lines = block.split(/\n/);
        const trimmed = lines.map((line) => line.trim());
        const isList = trimmed.length > 1 && trimmed.every((line) => /^[-•\u2022]/.test(line));

        if (isList) {
          const ul = document.createElement('ul');
          trimmed.forEach((line) => {
            const li = document.createElement('li');
            li.textContent = line.replace(/^[-•\u2022\s]+/, '');
            ul.appendChild(li);
          });
          fragment.appendChild(ul);
        } else {
          const p = document.createElement('p');
          lines.forEach((line, index) => {
            if (index > 0) {
              p.appendChild(document.createElement('br'));
            }
            p.appendChild(document.createTextNode(line));
          });
          fragment.appendChild(p);
        }
      });

      return fragment;
    }
  }

  window.DigiAIChatbot = DigiAIChatbot;

  document.addEventListener('DOMContentLoaded', function() {
    const containers = document.querySelectorAll('[data-digiai-chatbot]');
    containers.forEach((container) => {
      const chatbot = new DigiAIChatbot(container);
      chatbot.init();
    });
  });
})(window, document);
