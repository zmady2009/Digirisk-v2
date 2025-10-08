/**
 * Initialise l'objet "digiai" ainsi que la méthode "init" obligatoire pour la bibliothèque DigiriskDolibarr.
 *
 * @since   21.1.0
 * @version 21.1.0
 */
window.digiriskdolibarr.digiai = {
  state: {
    history: [],
    lastAnalysis: null
  }
};

/**
 * La méthode appelée automatiquement par la bibliothèque DigiriskDolibarr.
 *
 * @since   21.1.0
 * @version 21.1.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.init = function() {
  window.digiriskdolibarr.digiai.event();
  window.digiriskdolibarr.digiai.initTabs();
  window.digiriskdolibarr.digiai.restoreHistory();
  // window.digiriskdolibarr.digiai.bypassSaturneForDigiAI();
};

/**
 * La méthode contenant tous les événements pour digiai.
 *
 * @since   21.1.0
 * @version 21.1.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.event = function() {
  $(document).on('submit', '#analyze_text_form', window.digiriskdolibarr.digiai.submitTextForm);
  $(document).on('click', '.digiAI-tab-button', window.digiriskdolibarr.digiai.switchTab);
  $(document).on('click', '.open-analyze-image-modal', window.digiriskdolibarr.digiai.changeModalButton);
  $(document).on('click', '.analyze-image', window.digiriskdolibarr.digiai.submitImageForm);
  $( document ).on( 'click', '.clickable-photo', window.digiriskdolibarr.digiai.selectPhoto );
};

/**
 * Initialise la gestion des onglets
 *
 * @since   21.1.0
 * @version 21.1.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.initTabs = function() {
  $('.digiAI-tab-button').first().addClass('active');
  $('.digiAI-tab-content').first().addClass('active');
};

/**
 * Gère le changement d'onglets
 *
 * @since   21.1.0
 * @version 21.1.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.switchTab = function(e) {
  e.preventDefault();

  const targetTab = $(this).attr('data-tab');

  $('.digiAI-tab-button').removeClass('active');
  $('.digiAI-tab-content').removeClass('active');

  $(this).addClass('active');
  $('#' + targetTab).addClass('active');
};

/**
 * Restore DigiAI local history from storage.
 *
 * @since   23.0.0
 * @version 23.0.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.restoreHistory = function() {
  try {
    const saved = localStorage.getItem('digiai.history');
    if (!saved) {
      return;
    }

    const parsed = JSON.parse(saved);
    if (Array.isArray(parsed)) {
      window.digiriskdolibarr.digiai.state.history = parsed;
    }
    window.digiriskdolibarr.digiai.renderHistory();
  } catch (error) {
    console.warn('Unable to restore DigiAI history', error);
  }
};

/**
 * Persist current DigiAI history to storage.
 *
 * @since   23.0.0
 * @version 23.0.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.persistHistory = function() {
  try {
    localStorage.setItem('digiai.history', JSON.stringify(window.digiriskdolibarr.digiai.state.history));
  } catch (error) {
    console.warn('Unable to persist DigiAI history', error);
  }
};

/**
 * Render local analysis history list if the placeholder exists.
 *
 * @since   23.0.0
 * @version 23.0.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.renderHistory = function() {
  const container = document.querySelector('[data-digiai-history]');
  if (!container) {
    return;
  }

  container.innerHTML = '';
  window.digiriskdolibarr.digiai.state.history.forEach((entry, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button small digiai-history-entry';
    button.textContent = `${entry.label} — ${entry.timestamp}`;
    button.addEventListener('click', () => {
      window.digiriskdolibarr.digiai.displayResults(entry.payload, true);
    });

    container.appendChild(button);
    if (index < window.digiriskdolibarr.digiai.state.history.length - 1) {
      container.appendChild(document.createElement('br'));
    }
  });
};

/**
 * Pushes a new entry in history and persists it.
 *
 * @param {Object} payload
 * @return {void}
 */
window.digiriskdolibarr.digiai.pushHistoryEntry = function(payload) {
  const history = window.digiriskdolibarr.digiai.state.history;
  const metadata = payload && payload.metadata ? payload.metadata : {};
  const entry = {
    label: metadata.label || window.digiriskdolibarr.digiai.buildDefaultLabel(payload),
    timestamp: new Date().toLocaleString(),
    payload: payload
  };

  history.unshift(entry);

  if (history.length > 10) {
    history.length = 10;
  }

  window.digiriskdolibarr.digiai.persistHistory();
  window.digiriskdolibarr.digiai.renderHistory();
};

/**
 * Builds a default label for an analysis result.
 *
 * @param {Object} payload
 * @return {string}
 */
window.digiriskdolibarr.digiai.buildDefaultLabel = function(payload) {
  const riskCount = payload && Array.isArray(payload.risks) ? payload.risks.length : 0;
  const summaries = payload && Array.isArray(payload.summaries) ? payload.summaries.join(', ') : '';
  return `Analyse (${riskCount} risques) ${summaries}`.trim();
};

/**
 * Select photo
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.selectPhoto = function( event ) {
  $('.analyze-image').removeClass('button-disable');
};

/**
 * Bypass complètement Saturne pour DigiAI
 *
 * @since   21.1.0
 * @version 21.1.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.changeModalButton = function() {
  $('.modal-photo[data-from-type="digiAi"]')
    .find('.save-photo')
    .removeClass('save-photo')
    .addClass('analyze-image');
};

/**
 * Méthode pour gérer le formulaire de soumission du fichier image et l'analyse directe par ChatGPT.
 *
 * @since   21.1.0
 * @version 21.1.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.submitImageForm = async function(e) {
  e.preventDefault();

  window.digiriskdolibarr.digiai.resetModal('image');
  let mediaGallery = $('#media_gallery');
  let mediaGalleryModal = $(this).closest('.modal-container');
  mediaGallery.removeClass('modal-active');

  let fileLinked = mediaGalleryModal.find('.clicked-photo').first().find('.filename').val();
  let imgSrc = mediaGalleryModal.find('.clicked-photo').first().find('img').attr('src');

  if (!fileLinked) {
    alert('Veuillez sélectionner une image.');
    return;
  }

  $('#uploaded-image-preview').attr('src', imgSrc).css('opacity', 1).show();
  $('#analyzed-text-preview').hide();
  $('.analysis-in-progress').show().css('opacity', 1);
  $('.analysis-result').hide();

  $('#digiai_modal').addClass('modal-active');

  try {
    const response = await fetch(imgSrc);
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const blob = await response.blob();

    const formData = new FormData();
    formData.append('image_file', blob, fileLinked);
    formData.append('action', 'analyze_image');

    await window.digiriskdolibarr.digiai.getChatGptResponse(formData);

  } catch (error) {
    console.error('Erreur lors du chargement de l\'image:', error);
    alert('Erreur lors du chargement de l\'image depuis la bibliothèque de médias');

    $('#digiai_modal').removeClass('modal-active');
  }
};

/**
 * Méthode pour gérer le formulaire d'analyse de texte
 *
 * @since   21.1.0
 * @version 21.1.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.submitTextForm = async function(e) {
  e.preventDefault();

  const textArea = $('#analysis_text');
  const text = textArea.val().trim();

  if (!text) {
    alert('Veuillez saisir du texte à analyser');
    return;
  }

  window.digiriskdolibarr.digiai.resetModal('text');

  $('#uploaded-image-preview').hide();
  $('#analyzed-text-preview').show();
  $('.text-preview-content').html(text.replace(/\n/g, '<br>'));

  $('.digiai-loader-text').text('Analyse en cours du texte...');

  $('#digiai_modal').addClass('modal-active');

  let formData = new FormData();
  formData.append('action', 'analyze_text');
  formData.append('analysis_text', text);

  await window.digiriskdolibarr.digiai.getChatGptResponse(formData);

};

/**
 * Récupère la réponse de ChatGPT
 *
 * @since   21.1.0
 * @version 21.1.0
 *
 *
 */
window.digiriskdolibarr.digiai.getChatGptResponse = async function(formData) {

  let token = '';
  try {
    if (window.saturne && window.saturne.toolbox && typeof window.saturne.toolbox.getToken === 'function') {
      token = window.saturne.toolbox.getToken();
    }
  } catch (error) {
    console.warn('DigiAI token unavailable', error);
  }

  try {
    const chatGptResponse = await fetch('backend_endpoint_for_chatgpt.php?token=' + encodeURIComponent(token || ''), {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    if (!chatGptResponse || typeof chatGptResponse.ok === 'undefined') {
      throw new Error('Réponse réseau DigiAI invalide');
    }

    let chatGptData;
    try {
      chatGptData = await chatGptResponse.json();
    } catch (parseError) {
      throw new Error('Réponse DigiAI illisible');
    }

    if (!chatGptResponse.ok || !chatGptData || !chatGptData.success) {
      const message = chatGptData && chatGptData.error ? chatGptData.error : 'Erreur lors de l\'appel DigiAI';
      throw new Error(message);
    }

    window.digiriskdolibarr.digiai.state.lastAnalysis = {
      payload: chatGptData.data,
      timestamp: new Date().toISOString()
    };

    window.digiriskdolibarr.digiai.displayResults(chatGptData.data);
    window.digiriskdolibarr.digiai.pushHistoryEntry(chatGptData.data);

  } catch (error) {
    console.error('DigiAI error', error);
    alert(error.message || 'Erreur inconnue DigiAI');
    $('#digiai_modal').removeClass('modal-active');
  }
};

/**
 * Affiche les résultats d'analyse dans le tableau
 *
 * @since   21.1.0
 * @version 21.1.0
 *
 * @param {Array} risks - Liste des risques détectés
 * @return {void}
 */
window.digiriskdolibarr.digiai.displayResults = function(payload, fromHistory) {
  if (typeof fromHistory === 'undefined') {
    fromHistory = false;
  }
  $('.modal-analyse-phase').fadeOut(400, function () {
    $('.modal-result-phase').fadeIn(400);
  });

  let table = $('#risque_table');
  table.css('display', 'table');
  let tbody = table.find('tbody');
  tbody.empty();

  let dolUrlRoot = $('#dol_url_root').val();
  const categoryMap = window.digiriskdolibarr.categoryMap;

  let risks = [];
  if (payload && Array.isArray(payload.risks)) {
    risks = payload.risks;
  } else if (Array.isArray(payload)) {
    risks = payload;
  }

  risks.forEach((risque, index) => {
    let tr = $('<tr class="oddeven" id="new_risk' + index + '">');

    let title = risque.title;
    tr.attr('data-category', title);

    let cotation = parseInt(risque.cotation);
    let description = risque.description;
    let prevention_actions = risque.prevention_actions;

    let descInput = window.digiriskdolibarr.risk_table_common.createDescriptionTextarea(description);
    let cotationInput = window.digiriskdolibarr.risk_table_common.createCotationElement(cotation);
    let actionsContainer = window.digiriskdolibarr.risk_table_common.createActionsContainer(prevention_actions);

    let riskImgContainer = window.digiriskdolibarr.risk_table_common.createCategoryImage(
      dolUrlRoot + '/custom/digiriskdolibarr/img/categorieDangers/' + title + '.png',
      categoryMap[title] || 'Catégorie inconnue'
    );

    let checkbox = window.digiriskdolibarr.risk_table_common.createCheckbox('select-risk', 'submit_selected_risks');

    tr.append($('<td>').append(checkbox));
    tr.append($('<td>').append(riskImgContainer));
    tr.append($('<td>').append(cotationInput));
    tr.append($('<td>').append(descInput));
    tr.append($('<td>').append(actionsContainer));

    tbody.append(tr);
  });

  // Gérer le bouton de soumission
  window.digiriskdolibarr.digiai.handleSubmitButton();

  window.digiriskdolibarr.digiai.renderMetadata(payload);
  if (!fromHistory) {
    window.digiriskdolibarr.digiai.highlightNewResults();
  }
};

/**
 * Render metadata, recommendations and summaries when placeholders are present.
 *
 * @param {Object} payload
 * @return {void}
 */
window.digiriskdolibarr.digiai.renderMetadata = function(payload) {
  if (!payload) {
    return;
  }

  const recommendationContainer = document.querySelector('[data-digiai-recommendations]');
  if (recommendationContainer) {
    recommendationContainer.innerHTML = '';
    const list = document.createElement('ul');
    (Array.isArray(payload.recommendations) ? payload.recommendations : []).forEach((item) => {
      const li = document.createElement('li');
      li.textContent = item;
      list.appendChild(li);
    });
    recommendationContainer.appendChild(list);
  }

  const summaryContainer = document.querySelector('[data-digiai-summaries]');
  if (summaryContainer) {
    summaryContainer.textContent = (Array.isArray(payload.summaries) ? payload.summaries.join(' / ') : '');
  }

  const confidenceContainer = document.querySelector('[data-digiai-confidence]');
  if (confidenceContainer && payload.metadata && typeof payload.metadata.confidence !== 'undefined') {
    confidenceContainer.textContent = payload.metadata.confidence + '%';
  }
};

/**
 * Adds a temporary highlight animation to new table rows.
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.highlightNewResults = function() {
  $('#risque_table tbody tr').addClass('digiai-highlight');
  setTimeout(function() {
    $('#risque_table tbody tr').removeClass('digiai-highlight');
  }, 2000);
};

/**
 * Gère le bouton de soumission des risques sélectionnés
 *
 * @since   21.1.0
 * @version 21.1.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.handleSubmitButton = function() {
  let token = window.saturne.toolbox.getToken();
  let dolUrlRoot = $('#dol_url_root').val();

  $('#submit_selected_risks').off('click').on('click', function () {
    const selectedRows = $('input.select-risk:checked').closest('tr');

    if (selectedRows.length === 0) {
      return;
    }

    selectedRows.each(function () {
      const row = $(this);
      const riskData = window.digiriskdolibarr.risk_table_common.extractRiskDataFromRow(row);

      $.ajax({
        url: dolUrlRoot + '/custom/digiriskdolibarr/core/ajax/create_risk.php?token=' + token,
        method: 'POST',
        data: JSON.stringify(riskData),
        contentType: 'application/json',
        dataType: 'json',
        cache: false,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        success: function() {
          const clonedRow = row.clone();

          clonedRow.find('.select-risk').remove();
          clonedRow.find('textarea, input[type="text"], input[type="number"]').prop('disabled', true);
          clonedRow.find('.risk-evaluation-cotation').each(function () {
            if (!$(this).hasClass('selected-cotation')) {
              $(this).hide();
            }
          });

          $('.risque-table .previous-risks-list').append(clonedRow);
          row.remove();

          // Vérifier s'il reste des risques à sélectionner
          const remainingRows = $('input.select-risk').length;
          if (remainingRows === 0) {
            $('#submit_selected_risks').prop('disabled', true).css('opacity', 0.6);
          }

          window.saturne.loader.remove($('#submit_selected_risks'))
          $('#submit_selected_risks').removeClass('button-disable');
        },
        error: function(xhr, status, error) {
          alert('Erreur lors de l\'ajout d\'un risque : ' + (xhr.responseText || error));
        }
      });
    });
  });
};

/**
 * Réinitialise complètement l'état de la modale DigiAI.
 *
 * @since   21.1.0
 * @version 21.1.0
 *
 * @param {string} type - Type d'analyse ('image' ou 'text')
 * @return {void}
 */
window.digiriskdolibarr.digiai.resetModal = function (type) {
  $('#digiai_modal').addClass('modal-active');
  $('.modal-analyse-phase').show();
  $('.modal-result-phase').hide();

  if (type === 'image') {
    $('#uploaded-image-preview').attr('src', '').show();
    $('#analyzed-text-preview').hide();
    $('.digiai-loader-text').text('Analyse en cours de l\'image...');
  } else if (type === 'text') {
    $('#uploaded-image-preview').hide();
    $('#analyzed-text-preview').show();
    $('.digiai-loader-text').text('Analyse en cours du texte...');
  }

  $('.analysis-in-progress').show().html(`
    <p class="digiai-loader-text">Analyse en cours...</p>
    <div class="loader"></div>
  `);
  $('#risque_table').hide().find('tbody').empty();
};

