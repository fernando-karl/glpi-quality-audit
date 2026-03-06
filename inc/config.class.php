<?php
/**
 * Configuration class with Entity support
 */
class PluginQualityauditConfig extends CommonDBTM {
   
   static $rightname = 'config';
   
   /**
    * Encrypt API key using GLPI's encryption key
    */
   static function encryptApiKey($api_key) {
      if (empty($api_key)) {
         return '';
      }
      // Use GLPI's secure password encryption if available
      if (function_exists('GLPIKey::getOrGenerateKey')) {
         return \GLPIKey::getOrGenerateKey()->encrypt($api_key);
      }
      // Fallback: base64 encode (not secure, but prevents plain storage)
      return base64_encode($api_key);
   }
   
   /**
    * Decrypt API key
    */
   static function decryptApiKey($encrypted_key) {
      if (empty($encrypted_key)) {
         return '';
      }
      if (function_exists('GLPIKey::getOrGenerateKey')) {
         try {
            return \GLPIKey::getOrGenerateKey()->decrypt($encrypted_key);
         } catch (Exception $e) {
            // Fallback: might be old base64 encoded
            return base64_decode($encrypted_key);
         }
      }
      return base64_decode($encrypted_key);
   }
   
   static function getTypeName($nb = 0) {
      return __('Quality Audit Configuration', 'qualityaudit');
   }
   
   /**
    * Get configuration for a specific entity
    * Implements inheritance: searches current entity, then parents recursively
    * 
    * @param int $entities_id Entity ID (default: current session entity)
    * @return array Configuration array
    */
   static function getConfig($entities_id = -1) {
      global $DB;
      
      // Use current entity if not specified
      if ($entities_id == -1) {
         $entities_id = $_SESSION['glpiactive_entity'] ?? 0;
      }
      
      // Build entity hierarchy (current + all parents)
      $entities = [];
      if (class_exists('Entity')) {
         $entities = getAncestorsOf('glpi_entities', $entities_id);
         $entities[] = $entities_id;
      } else {
         $entities = [$entities_id];
      }
      
      // Search for config in current entity first, then parents
      foreach (array_reverse($entities) as $entity_id) {
         $iterator = $DB->request([
            'FROM'   => 'glpi_plugin_qualityaudit_configs',
            'WHERE'  => [
               'entities_id' => $entity_id,
               'OR' => [
                  ['is_recursive' => 1],
                  ['entities_id' => $entities_id] // Exact match
               ]
            ],
            'ORDER'  => ['entities_id DESC'], // Prefer more specific (child) configs
            'LIMIT'  => 1
         ]);
         
         if (count($iterator)) {
            return $iterator->current();
         }
      }
      
      // Fallback: root entity config
      $iterator = $DB->request([
         'FROM'   => 'glpi_plugin_qualityaudit_configs',
         'WHERE'  => ['entities_id' => 0],
         'LIMIT'  => 1
      ]);
      
      if (count($iterator)) {
         return $iterator->current();
      }
      
      return [];
   }
   
   /**
    * Get configuration for a specific ticket's entity
    * 
    * @param CommonITILObject $item Ticket/Change/Problem object
    * @return array Configuration array
    */
   static function getConfigForItem($item) {
      $entity_id = $item->fields['entities_id'] ?? 0;
      return self::getConfig($entity_id);
   }
   
   /**
    * Update or create configuration for an entity
    * 
    * @param array $data Configuration data (must include entities_id)
    * @return bool Success
    */
   static function updateConfig($data) {
      global $DB;
      
      $entities_id = $data['entities_id'] ?? $_SESSION['glpiactive_entity'] ?? 0;
      
      // Check if config exists for this entity
      $iterator = $DB->request([
         'FROM'   => 'glpi_plugin_qualityaudit_configs',
         'WHERE'  => ['entities_id' => $entities_id],
         'LIMIT'  => 1
      ]);
      
      if (count($iterator)) {
         // Update existing config
         $config = $iterator->current();
         $data['id'] = $config['id'];
         $data['date_mod'] = $_SESSION['glpi_currenttime'];
         
         return $DB->update('glpi_plugin_qualityaudit_configs', $data, ['id' => $config['id']]);
      } else {
         // Create new config
         $data['entities_id'] = $entities_id;
         $data['date_mod'] = $_SESSION['glpi_currenttime'];
         
         return $DB->insert('glpi_plugin_qualityaudit_configs', $data);
      }
   }
   
   /**
    * Delete configuration for an entity
    * 
    * @param int $entities_id Entity ID
    * @return bool Success
    */
   static function deleteConfig($entities_id) {
      global $DB;
      
      return $DB->delete('glpi_plugin_qualityaudit_configs', ['entities_id' => $entities_id]);
   }
   
   /**
    * Get all configurations (for admin view)
    * 
    * @return array Array of configs
    */
   static function getAllConfigs() {
      global $DB;
      
      $configs = [];
      $iterator = $DB->request([
         'FROM'   => 'glpi_plugin_qualityaudit_configs',
         'ORDER'  => ['entities_id ASC']
      ]);
      
      foreach ($iterator as $data) {
         $configs[] = $data;
      }
      
      return $configs;
   }
   
   /**
    * Show config form with entity selector
    * 
    * @param int $ID Not used (kept for compatibility)
    * @param array $options Options array
    */
   function showForm($ID, $options = []) {
      global $CFG_GLPI;

      // Get entity from options or session
      $entities_id = $options['entities_id'] ?? $_SESSION['glpiactive_entity'] ?? 0;

      // Get config for this entity
      $config = self::getConfig($entities_id);

      // Decrypt API key for display (empty if not set)
      $display_api_key = '';
      $has_api_key = !empty($config['api_key']);
      if ($has_api_key) {
         $display_api_key = '********';
      }

      // Check if we have explicit config for this entity (vs inherited)
      $has_own_config = false;
      if (!empty($config) && $config['entities_id'] == $entities_id) {
         $has_own_config = true;
      }

      $threshold = (int)($config['approval_threshold'] ?? 80);
      $selected_types = explode(',', $config['audit_ticket_types'] ?? 'Ticket,Change,Problem');

      // Include config CSS
      echo "<link rel='stylesheet' href='" . $CFG_GLPI['root_doc'] . "/plugins/qualityaudit/css/config.css' />";

      echo "<form method='post' id='qa-config-form' action='" . $CFG_GLPI['root_doc'] . "/plugins/qualityaudit/front/config.form.php'>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo "<input type='hidden' name='entities_id' value='$entities_id' />";

      echo "<div class='qa-config-wrapper'>";

      // Header
      echo "<div class='qa-config-header'>";
      echo "<h2><i class='fas fa-cog' style='color:#6c757d;margin-right:8px;'></i>" . __('Quality Audit Configuration', 'qualityaudit') . "</h2>";
      echo "</div>";

      // Entity selector (full width above the grid)
      echo "<div class='qa-config-section' style='margin-bottom:24px;'>";
      echo "<div class='qa-field'>";
      echo "<label class='qa-field-label'>" . __('Entity', 'qualityaudit') . "</label>";
      Entity::dropdown([
         'name' => 'entities_id',
         'value' => $entities_id,
         'on_change' => 'this.form.submit()',
         'width' => '100%'
      ]);
      echo "<input type='hidden' name='view_entity' value='1' />";
      if (!$has_own_config && !empty($config)) {
         $inherited_entity = new Entity();
         $inherited_entity->getFromDB($config['entities_id']);
         echo "<span class='qa-field-inherited'>";
         echo "<i class='fas fa-level-up-alt'></i> " . sprintf(__('Inherited from: %s', 'qualityaudit'), htmlspecialchars($inherited_entity->fields['name']));
         echo "</span>";
      } else if ($has_own_config) {
         echo "<span class='qa-field-own'>";
         echo "<i class='fas fa-check-circle'></i> " . __('Specific configuration for this entity', 'qualityaudit');
         echo "</span>";
      }
      echo "</div>";

      echo "<div class='qa-field'>";
      echo "<label class='qa-field-label'>" . __('Apply to child entities', 'qualityaudit');
      echo " <span class='qa-tooltip-icon' data-tooltip='" . htmlspecialchars(__('If enabled, child entities without specific config will use this configuration', 'qualityaudit')) . "'>?</span>";
      echo "</label>";
      Dropdown::showYesNo('is_recursive', $config['is_recursive'] ?? 1);
      echo "</div>";
      echo "</div>";

      // Two-column grid
      echo "<div class='qa-config-grid'>";

      // ===== LEFT COLUMN: Connection =====
      echo "<div class='qa-config-section'>";
      echo "<div class='qa-config-section-title'><i class='fas fa-plug'></i> " . __('Connection', 'qualityaudit') . "</div>";

      // API Provider
      echo "<div class='qa-field'>";
      echo "<label class='qa-field-label'>" . __('API Provider', 'qualityaudit') . "</label>";
      $providers = [
         'openai'    => 'OpenAI (GPT-4)',
         'claude'    => 'Anthropic (Claude)',
         'gemini'    => 'Google (Gemini)'
      ];
      Dropdown::showFromArray('api_provider', $providers, [
         'value' => $config['api_provider'] ?? 'openai',
         'width' => '100%'
      ]);
      echo "</div>";

      // API Key with eye toggle and status badge
      echo "<div class='qa-field'>";
      echo "<label class='qa-field-label'>" . __('API Key', 'qualityaudit') . " <span class='qa-required'>*</span> ";
      if ($has_api_key) {
         echo "<span class='qa-apikey-badge configured'><i class='fas fa-check-circle'></i> " . __('Configured', 'qualityaudit') . "</span>";
      } else {
         echo "<span class='qa-apikey-badge not-configured'><i class='fas fa-exclamation-triangle'></i> " . __('Not configured', 'qualityaudit') . "</span>";
      }
      echo "</label>";
      echo "<div class='qa-apikey-wrapper'>";
      echo "<input type='password' name='api_key' id='qa-api-key-input' value='" . htmlspecialchars($display_api_key) . "' style='width:100%;padding:6px 36px 6px 10px;border:1px solid #ced4da;border-radius:4px;font-size:14px;' />";
      echo "<button type='button' class='qa-apikey-toggle' id='qa-toggle-key' title='" . __('Toggle visibility', 'qualityaudit') . "'><i class='fas fa-eye'></i></button>";
      echo "</div>";
      echo "<span class='qa-field-hint'>" . __('Leave empty to keep current key. Enter new key to update.', 'qualityaudit') . "</span>";
      echo "</div>";

      // AI Model
      echo "<div class='qa-field'>";
      echo "<label class='qa-field-label'>" . __('AI Model', 'qualityaudit') . "</label>";
      echo "<input type='text' name='api_model' value='" . htmlspecialchars($config['api_model'] ?? 'gpt-4o-mini') . "' style='width:100%;padding:6px 10px;border:1px solid #ced4da;border-radius:4px;font-size:14px;' />";
      echo "<span class='qa-field-hint'>" . __('Examples: gpt-4o-mini, claude-3-5-sonnet-20241022, gemini-1.5-flash', 'qualityaudit') . "</span>";
      echo "</div>";

      // Test API Connection (ghost button near the connection fields)
      echo "<button type='button' class='qa-btn-test' id='qa-test-api-btn'>";
      echo "<i class='fas fa-wifi'></i> " . __('Test API Connection', 'qualityaudit');
      echo "</button>";

      echo "</div>"; // end left column

      // ===== RIGHT COLUMN: Business Rules =====
      echo "<div class='qa-config-section'>";
      echo "<div class='qa-config-section-title'><i class='fas fa-sliders-h'></i> " . __('Business Rules', 'qualityaudit') . "</div>";

      // Toggle rows for Auto Audit, Re-audit, Notification
      echo "<div class='qa-toggle-row'>";
      echo "<div class='qa-toggle-info'>";
      echo "<label class='qa-field-label'>" . __('Auto Audit on Solution Close', 'qualityaudit');
      echo " <span class='qa-tooltip-icon' data-tooltip='" . htmlspecialchars(__('Automatically evaluates solution quality when a ticket is closed', 'qualityaudit')) . "'>?</span>";
      echo "</label>";
      echo "</div>";
      Dropdown::showYesNo('auto_audit', $config['auto_audit'] ?? 1);
      echo "</div>";

      echo "<div class='qa-toggle-row'>";
      echo "<div class='qa-toggle-info'>";
      echo "<label class='qa-field-label'>" . __('Re-audit on Solution Update', 'qualityaudit');
      echo " <span class='qa-tooltip-icon' data-tooltip='" . htmlspecialchars(__('When a technician updates an existing solution, a new audit will be triggered automatically', 'qualityaudit')) . "'>?</span>";
      echo "</label>";
      echo "</div>";
      Dropdown::showYesNo('reaudit_on_update', $config['reaudit_on_update'] ?? 0);
      echo "</div>";

      // Threshold Slider
      echo "<div class='qa-field' style='margin-top:18px;'>";
      echo "<label class='qa-field-label'>" . __('Approval Threshold (Score)', 'qualityaudit');
      echo " <span class='qa-tooltip-icon' data-tooltip='" . htmlspecialchars(__('Solutions with score >= threshold will be approved. Below this value they are refused.', 'qualityaudit')) . "'>?</span>";
      echo "</label>";
      echo "<div class='qa-threshold-wrapper'>";
      echo "<input type='range' class='qa-threshold-slider' id='qa-threshold-slider' min='0' max='100' value='$threshold' />";
      echo "<input type='number' class='qa-threshold-number' name='approval_threshold' id='qa-threshold-number' value='$threshold' min='0' max='100' />";
      echo "</div>";
      echo "<span class='qa-field-hint'>" . __('Solutions with score >= threshold will be approved', 'qualityaudit') . "</span>";
      echo "</div>";

      // Ticket Types as Chips
      echo "<div class='qa-field'>";
      echo "<label class='qa-field-label'>" . __('Ticket Types to Audit', 'qualityaudit') . "</label>";
      echo "<div class='qa-chips-group'>";
      $types = [
         'Ticket'  => ['label' => __('Incidents', 'qualityaudit'), 'icon' => 'fas fa-ticket-alt'],
         'Change'  => ['label' => __('Changes', 'qualityaudit'), 'icon' => 'fas fa-exchange-alt'],
         'Problem' => ['label' => __('Problems', 'qualityaudit'), 'icon' => 'fas fa-exclamation-circle']
      ];
      foreach ($types as $key => $type) {
         $checked = in_array($key, $selected_types) ? 'checked' : '';
         $active_class = in_array($key, $selected_types) ? ' active' : '';
         echo "<label class='qa-chip$active_class' data-chip='$key'>";
         echo "<input type='checkbox' name='audit_ticket_types[]' value='" . htmlspecialchars($key) . "' $checked />";
         echo "<span class='qa-chip-check'><i class='fas fa-check' style='font-size:8px;'></i></span>";
         echo "<i class='" . $type['icon'] . "'></i> " . htmlspecialchars($type['label']);
         echo "</label>";
      }
      echo "</div>";
      echo "</div>";

      echo "</div>"; // end right column
      echo "</div>"; // end grid

      // Footer with action buttons
      echo "<div class='qa-config-footer'>";
      if ($has_own_config && $entities_id != 0) {
         echo "<button type='submit' name='delete_config' class='qa-btn-delete' onclick='return confirm(\"" . __s('Are you sure?') . "\");'>";
         echo "<i class='fas fa-trash-alt'></i> " . __s('Delete and use inherited config');
         echo "</button>";
      }
      echo "<button type='submit' name='update_config' class='qa-btn-save'>";
      echo "<i class='fas fa-save'></i> " . __s('Save');
      echo "</button>";
      echo "</div>";

      echo "</div>"; // end wrapper
      Html::closeForm();

      // Modal for Test API Connection
      echo <<<HTML
      <div id="qa-test-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center;">
         <div class="qa-modal-inner" style="background:#fff; border-radius:12px; padding:30px; max-width:480px; width:90%; margin:auto; position:relative; top:50%; transform:translateY(-50%); box-shadow:0 8px 30px rgba(0,0,0,0.2);">
            <h3 style="margin-top:0;" id="qa-modal-title"><i class="fas fa-wifi" style="margin-right:8px;color:#6c757d;"></i>Test API Connection</h3>
            <div id="qa-modal-body" style="min-height:60px;">
               <div id="qa-modal-spinner" style="text-align:center; padding:20px;">
                  <i class="fas fa-spinner fa-spin fa-2x"></i>
                  <p style="margin-top:10px;">Testing...</p>
               </div>
               <div id="qa-modal-result" style="display:none;"></div>
            </div>
            <div style="text-align:right; margin-top:15px;">
               <button type="button" class="vsubmit" id="qa-modal-close">Close</button>
            </div>
         </div>
      </div>
HTML;

      $test_url = $CFG_GLPI['root_doc'] . "/plugins/qualityaudit/front/test.php";
      echo "<script>
      (function() {
         // API Key visibility toggle
         var toggleBtn = document.getElementById('qa-toggle-key');
         var keyInput = document.getElementById('qa-api-key-input');
         if (toggleBtn && keyInput) {
            toggleBtn.addEventListener('click', function() {
               var icon = toggleBtn.querySelector('i');
               if (keyInput.type === 'password') {
                  keyInput.type = 'text';
                  icon.className = 'fas fa-eye-slash';
               } else {
                  keyInput.type = 'password';
                  icon.className = 'fas fa-eye';
               }
            });
         }

         // Threshold slider sync
         var slider = document.getElementById('qa-threshold-slider');
         var number = document.getElementById('qa-threshold-number');
         if (slider && number) {
            slider.addEventListener('input', function() { number.value = slider.value; });
            number.addEventListener('input', function() { slider.value = number.value; });
         }

         // Chip toggle
         document.querySelectorAll('.qa-chip').forEach(function(chip) {
            chip.addEventListener('click', function(e) {
               // Let the hidden checkbox toggle naturally
               var cb = chip.querySelector('input[type=checkbox]');
               if (e.target !== cb) {
                  cb.checked = !cb.checked;
               }
               chip.classList.toggle('active', cb.checked);
            });
         });

         // Test API modal
         var btn = document.getElementById('qa-test-api-btn');
         var modal = document.getElementById('qa-test-modal');
         var closeBtn = document.getElementById('qa-modal-close');
         var spinner = document.getElementById('qa-modal-spinner');
         var result = document.getElementById('qa-modal-result');

         btn.addEventListener('click', function(e) {
            e.preventDefault();
            modal.style.display = 'flex';
            spinner.style.display = 'block';
            result.style.display = 'none';

            var form = document.getElementById('qa-config-form');
            var apiKey = '';
            var apiProvider = '';
            var apiModel = '';
            if (form) {
               var kI = form.querySelector('[name=api_key]');
               var pS = form.querySelector('[name=api_provider]');
               var mI = form.querySelector('[name=api_model]');
               if (kI) apiKey = kI.value;
               if (pS) apiProvider = pS.value;
               if (mI) apiModel = mI.value;
            }

            var body = new URLSearchParams();
            body.append('entities_id', '" . (int)$entities_id . "');
            body.append('api_key', apiKey);
            body.append('api_provider', apiProvider);
            body.append('api_model', apiModel);

            var csrfInput = form ? form.querySelector('[name=_glpi_csrf_token]') : null;
            if (csrfInput) {
               body.append('_glpi_csrf_token', csrfInput.value);
            }
            var headers = {
               'Content-Type': 'application/x-www-form-urlencoded',
               'X-Requested-With': 'XMLHttpRequest'
            };
            if (csrfInput) {
               headers['X-Glpi-Csrf-Token'] = csrfInput.value;
            }

            fetch('" . addslashes($test_url) . "', {
               method: 'POST',
               credentials: 'same-origin',
               headers: headers,
               body: body.toString()
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
               spinner.style.display = 'none';
               result.style.display = 'block';
               if (data.success) {
                  result.innerHTML = '<div style=\"padding:15px; background:#d4edda; border:1px solid #c3e6cb; border-radius:6px; color:#155724;\">'
                     + '<strong><i class=\"fas fa-check-circle\"></i> ' + data.message + '</strong><br>'
                     + '<small>Provider: ' + (data.provider || '-') + ' | Model: ' + (data.model || '-') + '</small>'
                     + '</div>';
               } else {
                  result.innerHTML = '<div style=\"padding:15px; background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px; color:#721c24;\">'
                     + '<strong><i class=\"fas fa-times-circle\"></i> ' + (data.message || 'Connection failed') + '</strong>'
                     + '</div>';
               }
            })
            .catch(function(err) {
               spinner.style.display = 'none';
               result.style.display = 'block';
               result.innerHTML = '<div style=\"padding:15px; background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px; color:#721c24;\">'
                  + '<strong><i class=\"fas fa-times-circle\"></i> Request failed: ' + err.message + '</strong>'
                  + '</div>';
            });
         });

         closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
         });

         modal.addEventListener('click', function(e) {
            if (e.target === modal) {
               modal.style.display = 'none';
            }
         });
      })();
      </script>";

      // Show configured entities quick-jump list
      if (Session::haveRight('config', UPDATE)) {
         self::showConfiguredEntities($entities_id);
         self::showInheritanceTree($entities_id);
      }
   }
   
   /**
    * Show quick-jump list of all entities with explicit configs
    *
    * @param int $entities_id Current entity ID
    */
   static function showConfiguredEntities($entities_id) {
      global $CFG_GLPI;

      $configs = self::getAllConfigs();
      if (empty($configs)) {
         return;
      }

      echo "<div class='qa-entities-section'>";
      echo "<h3><i class='fas fa-sitemap' style='color:#6c757d;margin-right:6px;'></i>" . __('Configured Entities', 'qualityaudit') . "</h3>";
      echo "<table class='qa-entities-table'>";
      echo "<thead><tr>";
      echo "<th>" . __('Entity', 'qualityaudit') . "</th>";
      echo "<th>" . __('API Provider', 'qualityaudit') . "</th>";
      echo "<th>" . __('Model', 'qualityaudit') . "</th>";
      echo "<th>" . __('Last Modified', 'qualityaudit') . "</th>";
      echo "</tr></thead><tbody>";

      foreach ($configs as $cfg) {
         $entity = new Entity();
         $entity->getFromDB($cfg['entities_id']);
         $entity_name = $entity->fields['name'] ?? __('Root entity', 'qualityaudit');
         $is_current = ($cfg['entities_id'] == $entities_id);
         $row_class = $is_current ? " class='qa-row-current'" : "";

         $link = $CFG_GLPI['root_doc'] . "/plugins/qualityaudit/front/config.form.php?entities_id=" . (int)$cfg['entities_id'];

         echo "<tr$row_class>";
         echo "<td><a href='" . htmlspecialchars($link) . "'>" . htmlspecialchars($entity_name) . "</a>";
         if ($is_current) {
            echo " <i class='fas fa-arrow-left' style='font-size:10px;color:#007bff;'></i>";
         }
         echo "</td>";
         echo "<td>" . htmlspecialchars($cfg['api_provider'] ?? 'N/A') . "</td>";
         echo "<td>" . htmlspecialchars($cfg['api_model'] ?? 'N/A') . "</td>";
         echo "<td>" . htmlspecialchars($cfg['date_mod'] ?? '-') . "</td>";
         echo "</tr>";
      }

      echo "</tbody></table>";
      echo "</div>";
   }

   /**
    * Show inheritance tree for entity
    * 
    * @param int $entities_id Current entity ID
    */
   static function showInheritanceTree($entities_id) {
      global $DB, $CFG_GLPI;

      echo "<div class='qa-entities-section'>";
      echo "<h3><i class='fas fa-project-diagram' style='color:#6c757d;margin-right:6px;'></i>" . __('Configuration Inheritance Tree', 'qualityaudit') . "</h3>";
      echo "<table class='qa-entities-table'>";
      echo "<thead><tr>";
      echo "<th>" . __('Entity', 'qualityaudit') . "</th>";
      echo "<th>" . __('Has Config', 'qualityaudit') . "</th>";
      echo "<th>" . __('Recursive', 'qualityaudit') . "</th>";
      echo "<th>" . __('API Provider', 'qualityaudit') . "</th>";
      echo "</tr></thead><tbody>";

      // Get ancestors
      $ancestors = getAncestorsOf('glpi_entities', $entities_id);
      $ancestors[] = $entities_id;

      foreach (array_reverse($ancestors) as $entity_id) {
         $entity = new Entity();
         $entity->getFromDB($entity_id);

         $iterator = $DB->request([
            'FROM'   => 'glpi_plugin_qualityaudit_configs',
            'WHERE'  => ['entities_id' => $entity_id],
            'LIMIT'  => 1
         ]);

         $has_config = count($iterator) > 0;
         $config_data = $has_config ? $iterator->current() : [];

         $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $entity->fields['level'] ?? 0);
         $is_current = ($entity_id == $entities_id);
         $row_class = $is_current ? " class='qa-row-current'" : "";

         echo "<tr$row_class>";
         echo "<td>$indent";
         if ($has_config) {
            $link = $CFG_GLPI['root_doc'] . "/plugins/qualityaudit/front/config.form.php?entities_id=" . (int)$entity_id;
            echo "<a href='" . htmlspecialchars($link) . "'>" . htmlspecialchars($entity->fields['name']) . "</a>";
         } else {
            echo htmlspecialchars($entity->fields['name']);
         }
         if ($is_current) {
            echo " <i class='fas fa-arrow-left' style='font-size:10px;color:#007bff;'></i>";
         }
         echo "</td>";
         echo "<td>";
         if ($has_config) {
            echo "<span class='qa-status-yes'><i class='fas fa-check-circle'></i> " . __('Yes', 'qualityaudit') . "</span>";
         } else {
            echo "<span class='qa-status-no'><i class='fas fa-times-circle'></i> " . __('No (inherited)', 'qualityaudit') . "</span>";
         }
         echo "</td>";
         echo "<td>";
         if ($has_config) {
            echo ($config_data['is_recursive'] ?? 0)
               ? "<span class='qa-status-yes'><i class='fas fa-check'></i></span>"
               : "<span class='qa-status-no'><i class='fas fa-times'></i></span>";
         } else {
            echo "<span style='color:#adb5bd;'>-</span>";
         }
         echo "</td>";
         echo "<td>" . ($has_config ? htmlspecialchars($config_data['api_provider'] ?? 'N/A') : "<span style='color:#adb5bd;'>-</span>") . "</td>";
         echo "</tr>";
      }

      echo "</tbody></table>";
      echo "</div>";
   }
}
