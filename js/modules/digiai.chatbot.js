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
    }

    init() {
      this.render();
      this.bindEvents();
    }

    render() {
      this.root.classList.add('digiai-chatbot');

      this.messageList = document.createElement('div');
      this.messageList.className = 'digiai-chatbot__messages';

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

      this.root.appendChild(this.messageList);
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
      wrapper.textContent = content;
      this.messageList.appendChild(wrapper);
    }

    scrollToBottom() {
      this.messageList.scrollTop = this.messageList.scrollHeight;
    }

    async sendToServer() {
      this.setLoading(true);
      try {
        const token = window.saturne && window.saturne.toolbox && window.saturne.toolbox.getToken ? window.saturne.toolbox.getToken() : '';
        const url = this.endpoint + (this.endpoint.indexOf('?') === -1 ? '?token=' + token : '&token=' + token);

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

        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.error || 'Erreur DigiAI');
        }

        const assistantMessages = Array.isArray(result.data.messages) ? result.data.messages : [];
        assistantMessages.forEach((message) => {
          this.pushMessage('assistant', message);
        });

        if (Array.isArray(result.data.recommendations) && result.data.recommendations.length > 0) {
          this.pushMessage('assistant', 'Actions proposées : ' + result.data.recommendations.join('; '));
        }

        if (Array.isArray(result.data.summaries) && result.data.summaries.length > 0) {
          this.pushMessage('assistant', 'Résumé : ' + result.data.summaries.join(' / '));
        }

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
