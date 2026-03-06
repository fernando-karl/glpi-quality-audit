/**
 * Quality Audit - Solution Validator for GLPi 11
 * Intercepts solution form submission, validates via AI, shows score panel.
 * Single document-level capturing submit handler — no duplicates.
 */
(function() {
   'use strict';

   var PLUGIN_URL = (function() {
      var root = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';
      return root + '/plugins/qualityaudit';
   })();

   var MIN_LENGTH = 2;
   var isValidating = false;
   var skipNextSubmit = false; // when true, next submit on a .itilsolution form passes through
   var stylesInjected = false;

   function log() {
      var args = ['[QualityAudit]'].concat(Array.prototype.slice.call(arguments));
      console.log.apply(console, args);
   }

   // ---- Styles ----
   function injectStyles() {
      if (stylesInjected) return;
      stylesInjected = true;
      var s = document.createElement('style');
      s.id = 'qa-validator-css';
      s.textContent = [
         '.qa-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.55);z-index:10000;display:flex;align-items:center;justify-content:center;animation:qa-fadeIn .2s}',
         '.qa-panel{background:#fff;border-radius:12px;max-width:680px;width:94%;max-height:88vh;overflow-y:auto;box-shadow:0 12px 40px rgba(0,0,0,.25);animation:qa-slideUp .25s ease}',
         '.qa-panel-header{padding:20px 24px 16px;border-bottom:1px solid #e9ecef;display:flex;align-items:center;justify-content:space-between}',
         '.qa-panel-header h3{margin:0;font-size:17px;font-weight:600}',
         '.qa-panel-body{padding:20px 24px}',
         '.qa-panel-footer{padding:16px 24px;border-top:1px solid #e9ecef;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}',
         '.qa-score-ring{width:90px;height:90px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 16px;border:5px solid}',
         '.qa-score-bar{height:8px;background:#e9ecef;border-radius:4px;overflow:hidden;margin:4px 0 0}',
         '.qa-score-fill{height:100%;border-radius:4px;transition:width .4s ease}',
         '.qa-criteria-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:16px 0}',
         '.qa-crit-item{background:#f8f9fa;border-radius:6px;padding:10px 12px;border-left:3px solid #dee2e6}',
         '.qa-crit-item .qa-crit-label{font-size:12px;font-weight:600;color:#495057;margin-bottom:4px}',
         '.qa-crit-item .qa-crit-value{font-size:14px;font-weight:700}',
         '.qa-analysis{background:#f8f9fa;border-radius:6px;padding:12px 14px;margin:14px 0;font-size:13px;line-height:1.6;color:#333}',
         '.qa-suggestion-box{background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:16px;margin:14px 0}',
         '.qa-suggestion-box h4{margin:0 0 8px;font-size:14px;font-weight:600;color:#e65100}',
         '.qa-suggestion-text{font-size:13px;line-height:1.6;color:#333;max-height:200px;overflow-y:auto;padding:10px;background:#fff;border-radius:4px;border:1px solid #f0f0f0}',
         '.qa-btn{padding:8px 20px;border-radius:5px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .15s;display:inline-flex;align-items:center;gap:4px}',
         '.qa-btn-primary{background:#007bff;color:#fff}.qa-btn-primary:hover{background:#0056b3}',
         '.qa-btn-success{background:#28a745;color:#fff}.qa-btn-success:hover{background:#1e7e34}',
         '.qa-btn-outline{background:transparent;border:1px solid #dee2e6;color:#495057}.qa-btn-outline:hover{background:#f8f9fa}',
         '.qa-btn-warning{background:#fd7e14;color:#fff}.qa-btn-warning:hover{background:#e8590c}',
         '.qa-spinner-wrap{text-align:center;padding:40px 20px}',
         '.qa-spinner{width:40px;height:40px;border:4px solid #e9ecef;border-top-color:#007bff;border-radius:50%;animation:qa-spin .8s linear infinite;margin:0 auto 12px}',
         '.qa-status-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 14px;border-radius:14px;font-size:13px;font-weight:700}',
         '.qa-approved{background:#d4edda;color:#155724}',
         '.qa-refused{background:#f8d7da;color:#721c24}',
         '@keyframes qa-fadeIn{from{opacity:0}to{opacity:1}}',
         '@keyframes qa-slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}',
         '@keyframes qa-spin{to{transform:rotate(360deg)}}',
         '@media(max-width:600px){.qa-criteria-grid{grid-template-columns:1fr}.qa-panel{width:98%;max-height:94vh}.qa-panel-footer{flex-direction:column}.qa-btn{width:100%;justify-content:center}}'
      ].join('\n');
      document.head.appendChild(s);
   }

   // ---- TinyMCE helpers ----
   function getMCE() {
      return window.tinyMCE || window.tinymce || null;
   }

   /**
    * Find the TinyMCE editor for the solution form.
    * Searches by iframe inside the .itilsolution container, then by active editor.
    */
   function findEditor(form) {
      var mce = getMCE();
      if (!mce) {
         log('TinyMCE global not available');
         return null;
      }
      if (!mce.editors || mce.editors.length === 0) {
         log('TinyMCE loaded, 0 editors');
         return null;
      }

      var container = form.closest('.itilsolution') || form;

      // Method 1: Find iframe with _ifr suffix inside container, derive editor id
      var iframes = container.querySelectorAll('iframe[id$="_ifr"]');
      for (var i = 0; i < iframes.length; i++) {
         var edId = iframes[i].id.replace(/_ifr$/, '');
         var ed = mce.get(edId);
         if (ed) {
            log('Editor found via iframe:', edId);
            return ed;
         }
      }

      // Method 2: Find textarea inside form, get editor by textarea id
      var textareas = form.querySelectorAll('textarea');
      for (var t = 0; t < textareas.length; t++) {
         var ed2 = mce.get(textareas[t].id);
         if (ed2) {
            log('Editor found via textarea:', textareas[t].id);
            return ed2;
         }
      }

      // Method 3: Active editor
      if (mce.activeEditor) {
         log('Using activeEditor:', mce.activeEditor.id);
         return mce.activeEditor;
      }

      // Method 4: Any editor with solution_content_ prefix
      for (var j = 0; j < mce.editors.length; j++) {
         if (mce.editors[j].id && mce.editors[j].id.indexOf('solution_content_') === 0) {
            log('Editor found via pattern:', mce.editors[j].id);
            return mce.editors[j];
         }
      }

      log('No editor found. IDs:', mce.editors.map(function(e) { return e.id; }));
      return null;
   }

   /**
    * Get plain text from solution. Tries: editor → iframe body → textarea.
    */
   function getText(form, editor) {
      var html = '';

      // From editor API
      if (editor) {
         try { html = editor.getContent() || ''; } catch (e) { html = ''; }
      }

      // From iframe body
      if (!html) {
         var container = form.closest('.itilsolution') || form;
         var iframes = container.querySelectorAll('iframe');
         for (var i = 0; i < iframes.length; i++) {
            try {
               var doc = iframes[i].contentDocument || iframes[i].contentWindow.document;
               if (doc && doc.body) {
                  var t = (doc.body.textContent || doc.body.innerText || '').trim();
                  if (t.length > 0) {
                     log('Text from iframe:', t.length, 'chars');
                     return t;
                  }
               }
            } catch (e) { /* cross-origin */ }
         }
      }

      // From textarea
      if (!html) {
         var tas = (form.closest('.itilsolution') || form).querySelectorAll('textarea[name="content"]');
         for (var j = 0; j < tas.length; j++) {
            if (tas[j].value && tas[j].value.trim()) {
               html = tas[j].value;
               break;
            }
         }
      }

      if (html) {
         var tmp = document.createElement('div');
         tmp.innerHTML = html;
         return (tmp.textContent || tmp.innerText || '').trim();
      }
      return '';
   }

   /**
    * Set content in the TinyMCE editor.
    * Uses GLPi's setRichTextEditorContent if available, otherwise editor.setContent.
    */
   function setContent(form, editor, html) {
      var container = form.closest('.itilsolution') || form;

      // Method 1: GLPi's own function
      if (typeof window.setRichTextEditorContent === 'function' && editor) {
         log('Using setRichTextEditorContent for', editor.id);
         window.setRichTextEditorContent(editor.id, html);
         return;
      }

      // Method 2: TinyMCE editor API
      if (editor) {
         log('Using editor.setContent for', editor.id);
         editor.setContent(html);
         editor.undoManager.add();
         return;
      }

      // Method 3: Write directly to iframe body (GLPi on-demand TinyMCE without registered editor)
      var iframes = container.querySelectorAll('iframe[id$="_ifr"]');
      if (iframes.length === 0) {
         iframes = container.querySelectorAll('iframe');
      }
      var wroteIframe = false;
      for (var i = 0; i < iframes.length; i++) {
         try {
            var doc = iframes[i].contentDocument || iframes[i].contentWindow.document;
            if (doc && doc.body) {
               doc.body.innerHTML = html;
               wroteIframe = true;
               log('Set content via iframe body:', iframes[i].id || 'anonymous');

               // Also try setRichTextEditorContent with derived editor id
               var edId = (iframes[i].id || '').replace(/_ifr$/, '');
               if (edId && typeof window.setRichTextEditorContent === 'function') {
                  try {
                     window.setRichTextEditorContent(edId, html);
                     log('Also called setRichTextEditorContent for', edId);
                  } catch(e2) { /* ignore */ }
               }
               break;
            }
         } catch (e) {
            log('iframe write failed (cross-origin?):', e.message);
         }
      }

      // Method 4: Also update textarea (keeps form data in sync)
      var tas = container.querySelectorAll('textarea[name="content"]');
      for (var j = 0; j < tas.length; j++) {
         tas[j].value = html;
      }

      if (wroteIframe) {
         log('Content set via iframe + textarea sync');
      } else {
         log('Set content via textarea only (no iframe found)');
      }
   }

   // ---- Panel ----
   function showPanel(html) {
      removePanel();
      var overlay = document.createElement('div');
      overlay.className = 'qa-overlay';
      overlay.id = 'qa-overlay';
      overlay.innerHTML = '<div class="qa-panel">' + html + '</div>';
      document.body.appendChild(overlay);
      overlay.addEventListener('click', function(e) {
         if (e.target === overlay) removePanel();
      });
   }

   function removePanel() {
      var el = document.getElementById('qa-overlay');
      if (el) el.remove();
   }

   function showLoading() {
      showPanel(
         '<div class="qa-panel-header"><h3><i class="ti ti-clipboard-check" style="margin-right:8px;color:#007bff"></i>Quality Audit</h3></div>' +
         '<div class="qa-panel-body"><div class="qa-spinner-wrap">' +
         '<div class="qa-spinner"></div>' +
         '<p style="margin:0;color:#6c757d;font-size:14px">Avaliando qualidade da solucao...</p>' +
         '<p style="margin:6px 0 0;color:#adb5bd;font-size:12px">Isso pode levar alguns segundos</p>' +
         '</div></div>'
      );
   }

   function scoreColor(s) {
      return s >= 80 ? '#28a745' : s >= 60 ? '#ffc107' : '#dc3545';
   }

   function escapeHtml(t) {
      if (!t) return '';
      var d = document.createElement('div');
      d.textContent = t;
      return d.innerHTML;
   }

   // ---- Results ----
   function showResults(data, form, editor) {
      var score = data.score || 0;
      var isApproved = data.valid;
      var threshold = data.threshold || 80;
      var color = scoreColor(score);
      var hasSuggestion = data.suggestion && data.suggestion.trim().length > 0;

      var labels = {
         ortografia: {icon: 'ti-typography', label: 'Ortografia e Gramatica', max: 20},
         completude: {icon: 'ti-file-text', label: 'Completude', max: 30},
         resolucao:  {icon: 'ti-check', label: 'Resolucao Efetiva', max: 25},
         clareza:    {icon: 'ti-message-circle', label: 'Clareza e Tom', max: 15},
         tecnica:    {icon: 'ti-tool', label: 'Adequacao Tecnica', max: 10}
      };

      var h = '';
      h += '<div class="qa-panel-header">';
      h += '<h3><i class="ti ti-clipboard-check" style="margin-right:8px;color:' + color + '"></i>Quality Audit</h3>';
      h += '<span class="qa-status-badge ' + (isApproved ? 'qa-approved' : 'qa-refused') + '">';
      h += (isApproved ? '<i class="ti ti-check"></i> APROVADO' : '<i class="ti ti-x"></i> RECUSADO');
      h += '</span></div>';

      h += '<div class="qa-panel-body">';
      h += '<div class="qa-score-ring" style="color:' + color + ';border-color:' + color + '">' + score + '</div>';
      h += '<p style="text-align:center;margin:0 0 16px;font-size:13px;color:#6c757d">Nota minima: ' + threshold + ' pontos</p>';

      if (data.criteria) {
         h += '<div class="qa-criteria-grid">';
         for (var key in labels) {
            if (!labels.hasOwnProperty(key)) continue;
            var info = labels[key];
            var val = (data.criteria[key] !== undefined) ? data.criteria[key] : 0;
            var pct = info.max > 0 ? Math.round((val / info.max) * 100) : 0;
            var cc = scoreColor(pct);
            h += '<div class="qa-crit-item" style="border-left-color:' + cc + '">';
            h += '<div class="qa-crit-label"><i class="ti ' + info.icon + '" style="margin-right:4px"></i>' + info.label + '</div>';
            h += '<div class="qa-crit-value" style="color:' + cc + '">' + val + '/' + info.max + '</div>';
            h += '<div class="qa-score-bar"><div class="qa-score-fill" style="width:' + pct + '%;background:' + cc + '"></div></div>';
            h += '</div>';
         }
         h += '</div>';
      }

      if (data.analysis) {
         h += '<div class="qa-analysis"><strong><i class="ti ti-report-analytics" style="margin-right:4px"></i>Analise:</strong><br>' + escapeHtml(data.analysis) + '</div>';
      }

      if (hasSuggestion) {
         var sDisp = data.suggestion_html || escapeHtml(data.suggestion);
         h += '<div class="qa-suggestion-box">';
         h += '<h4><i class="ti ti-bulb" style="margin-right:4px"></i>Sugestao de Texto pela IA</h4>';
         h += '<div class="qa-suggestion-text">' + sDisp + '</div>';
         h += '</div>';
      }

      h += '</div>';

      h += '<div class="qa-panel-footer">';
      if (!isApproved) {
         if (hasSuggestion) {
            h += '<button class="qa-btn qa-btn-success" id="qa-use-suggestion"><i class="ti ti-replace"></i> Usar Sugestao da IA</button>';
         }
         h += '<button class="qa-btn qa-btn-outline" id="qa-close-edit"><i class="ti ti-pencil"></i> Editar Manualmente</button>';
      } else {
         h += '<button class="qa-btn qa-btn-outline" id="qa-close-edit"><i class="ti ti-pencil"></i> Continuar Editando</button>';
         if (hasSuggestion) {
            h += '<button class="qa-btn qa-btn-primary" id="qa-use-suggestion"><i class="ti ti-replace"></i> Usar Sugestao da IA</button>';
         }
         h += '<button class="qa-btn qa-btn-success" id="qa-do-save"><i class="ti ti-send"></i> Salvar Solucao</button>';
      }
      h += '</div>';

      showPanel(h);

      // Use Suggestion: apply AI text, close modal, user re-submits for new validation
      var btnSugg = document.getElementById('qa-use-suggestion');
      if (btnSugg && hasSuggestion) {
         btnSugg.addEventListener('click', function() {
            var sug = data.suggestion;
            if (sug.indexOf('<') === -1) {
               sug = '<p>' + sug.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>') + '</p>';
            }
            setContent(form, editor, sug);
            removePanel();
            log('Suggestion applied, analyst can review and re-submit');
         });
      }

      // Close / Edit
      var btnClose = document.getElementById('qa-close-edit');
      if (btnClose) {
         btnClose.addEventListener('click', function() {
            removePanel();
         });
      }

      // Save (approved only) — submit the form, skipping validation
      var btnSave = document.getElementById('qa-do-save');
      if (btnSave) {
         btnSave.addEventListener('click', function() {
            log('Save approved, submitting form');
            doSubmit(form);
         });
      }
   }

   function showError(message, form) {
      var h = '';
      h += '<div class="qa-panel-header"><h3><i class="ti ti-alert-triangle" style="margin-right:8px;color:#ffc107"></i>Quality Audit</h3></div>';
      h += '<div class="qa-panel-body"><div style="text-align:center;padding:20px">';
      h += '<i class="ti ti-cloud-off" style="font-size:40px;color:#ffc107;display:block;margin-bottom:12px"></i>';
      h += '<p style="font-size:14px;color:#333;margin:0 0 8px">' + escapeHtml(message) + '</p>';
      h += '<p style="font-size:12px;color:#6c757d;margin:0">O servico de IA esta temporariamente indisponivel.</p>';
      h += '</div></div>';
      h += '<div class="qa-panel-footer">';
      h += '<button class="qa-btn qa-btn-outline" id="qa-err-close">Fechar</button>';
      h += '<button class="qa-btn qa-btn-warning" id="qa-err-bypass"><i class="ti ti-send"></i> Salvar Mesmo Assim</button>';
      h += '</div>';
      showPanel(h);

      document.getElementById('qa-err-close').addEventListener('click', removePanel);
      document.getElementById('qa-err-bypass').addEventListener('click', function() {
         doSubmit(form);
      });
   }

   /**
    * Submit the form bypassing our handler.
    * Sets skipNextSubmit flag, then uses requestSubmit with the submit button
    * so GLPi's data-submit-once handler sees the correct submitter.
    */
   function doSubmit(form) {
      skipNextSubmit = true;
      removePanel();

      var btn = form.querySelector('button[type="submit"][name="add"], button[type="submit"][name="update"]');
      if (btn) {
         log('Submitting via requestSubmit with button:', btn.name);
         // Sync TinyMCE content to textarea before submitting
         var mce = getMCE();
         if (mce && typeof mce.triggerSave === 'function') {
            mce.triggerSave();
         }
         // requestSubmit with submitter preserves the button name in POST data
         if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(btn);
         } else {
            btn.click();
         }
      } else {
         log('No button found, using form.submit()');
         form.submit();
      }
   }

   // ---- API call ----
   function callValidation(form, editor) {
      if (isValidating) return;

      var text = getText(form, editor);
      log('Text length:', text.length, '| Preview:', text.substring(0, 60));

      if (text.length < MIN_LENGTH) {
         // Almost empty — let the AI handle it for proper suggestion
         log('Text very short, will send to AI anyway');
      }

      isValidating = true;
      showLoading();

      var ticketId = '';
      var itemtype = 'Ticket';
      var iiInput = form.querySelector('input[name="items_id"]');
      var itInput = form.querySelector('input[name="itemtype"]');
      if (iiInput) ticketId = iiInput.value;
      if (itInput) itemtype = itInput.value;
      if (!ticketId) {
         ticketId = new URLSearchParams(window.location.search).get('id') || '0';
      }

      var headers = {
         'Content-Type': 'application/json',
         'X-Requested-With': 'XMLHttpRequest'
      };
      var csrf = form.querySelector('input[name="_glpi_csrf_token"]');
      if (csrf) headers['X-Glpi-Csrf-Token'] = csrf.value;

      var url = PLUGIN_URL + '/front/validate_solution.php';
      log('POST', url, '| ticket:', ticketId);

      fetch(url, {
         method: 'POST',
         credentials: 'same-origin',
         headers: headers,
         body: JSON.stringify({ solution_content: text, ticket_id: ticketId, itemtype: itemtype })
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
         isValidating = false;
         log('Response:', data.score, data.status || data.error);
         if (data.error && data.bypass) {
            showError(data.error, form);
         } else if (data.failsafe) {
            showError(data.error || 'Servico indisponivel', form);
         } else {
            showResults(data, form, editor);
         }
      })
      .catch(function(err) {
         isValidating = false;
         log('Fetch error:', err);
         showError('Erro de conexao: ' + err.message, form);
      });
   }

   // ---- Single submit handler (document capturing phase) ----
   function handleSubmit(e) {
      var form = e.target;
      if (form.tagName !== 'FORM') return;
      if (!form.closest('.itilsolution')) return;

      // Skip flag set by doSubmit — allow this one through
      if (skipNextSubmit) {
         skipNextSubmit = false;
         log('Skip flag set, allowing submit');
         return;
      }

      // Block and validate
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();

      log('Intercepted submit');

      var editor = findEditor(form);
      var text = getText(form, editor);

      if (text.length === 0) {
         log('Empty text, allowing submit as fallback');
         skipNextSubmit = true;
         var btn = form.querySelector('button[type="submit"]');
         if (btn) {
            if (typeof form.requestSubmit === 'function') {
               form.requestSubmit(btn);
            } else {
               btn.click();
            }
         }
         return;
      }

      callValidation(form, editor);
   }

   // ---- Init ----
   function init() {
      log('Init v1.0.6 | URL:', PLUGIN_URL);
      log('tinyMCE:', typeof window.tinyMCE, '| tinymce:', typeof window.tinymce);
      injectStyles();

      // Single handler on document (capturing phase) — catches all forms including AJAX-loaded
      document.addEventListener('submit', handleSubmit, true);

      log('Ready — listening for .itilsolution form submits');
   }

   if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
   } else {
      init();
   }

})();
