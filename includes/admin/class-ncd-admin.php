<?php
/**
* Admin Main Class
*
* Hauptklasse für den WordPress Admin-Bereich
*
* @package NewCustomerDiscount
* @subpackage Admin
* @since 0.0.1
*/

if (!defined('ABSPATH')) {
   exit;
}

class NCD_Admin {
   private $menu;
   private $pages = [];
   private $ajax;
   private $tab_manager;

   public function __construct() {
       if (WP_DEBUG) {
           error_log('Initializing NCD Admin');
       }

       try {
           $this->init_components();
           $this->init_hooks();

           if (WP_DEBUG) {
               error_log('NCD Admin initialized successfully');
           }
       } catch (Exception $e) {
           if (WP_DEBUG) {
               error_log('NCD Admin initialization failed: ' . $e->getMessage());
           }
       }
   }

   private function init_components() {
       $this->menu = new NCD_Admin_Menu();
       $this->tab_manager = new NCD_Admin_Tab_Manager();
       $this->init_pages();
       $this->ajax = new NCD_Admin_Ajax();

       if (WP_DEBUG) {
           error_log('Admin components initialized');
       }
   }

   private function init_pages() {
       try {
           $pages = [
               'customers' => new NCD_Admin_Customers(),
               'templates' => new NCD_Admin_Templates(),
               'settings' => new NCD_Admin_Settings(),
               'statistics' => new NCD_Admin_Statistics()
           ];
    
           foreach ($pages as $key => $page) {
               $this->pages[$key] = $page;
               $this->menu->add_page($key, $page);
           }
    
           if (WP_DEBUG) {
               error_log('Admin pages initialized: ' . implode(', ', array_keys($this->pages)));
           }
       } catch (Exception $e) {
           if (WP_DEBUG) {
               error_log('Failed to initialize admin pages: ' . $e->getMessage());
           }
           throw $e;
       }
   }

   private function init_hooks() {
       add_action('admin_menu', [$this->menu, 'register_menus']);
       add_action('admin_enqueue_scripts', [$this, 'enqueue_common_assets']);

       if (WP_DEBUG) {
           error_log('Admin hooks initialized');
       }
   }

   public function get_tab_manager() {
       return $this->tab_manager;
   }

   public function enqueue_common_assets($hook) {
       if (strpos($hook, 'new-customers') === false) {
           return;
       }

       if (WP_DEBUG) {
           error_log('Loading admin assets for hook: ' . $hook);
       }

       $asset_version = WP_DEBUG ? time() : NCD_VERSION;
       $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

       // Core Scripts registrieren
       $this->register_core_scripts($asset_version);

       // Styles registrieren
       $this->register_styles($asset_version);

       // Seitenspezifische Assets
       $this->register_page_specific_assets($current_page, $asset_version);

       // Scripts enqueuen
       $this->enqueue_required_scripts($current_page);

       if (WP_DEBUG) {
           error_log('Admin assets loaded with nonce: ' . wp_create_nonce('ncd-admin-nonce'));
           $this->debug_loaded_assets();
       }
   }

   private function register_core_scripts($version) {
       // Base Script
       wp_register_script(
           'ncd-admin-base',
           NCD_ASSETS_URL . 'js/core/admin-base.js',
           ['jquery'],
           $version,
           true
       );

       // Ajax Handler
       wp_register_script(
           'ncd-ajax-handler',
           NCD_ASSETS_URL . 'js/core/ajax-handler.js',
           ['jquery', 'ncd-admin-base'],
           $version,
           true
       );

       // Lokalisierung
       wp_localize_script('ncd-admin-base', 'ncdAdmin', $this->get_admin_localization());

       // Base Script aktivieren
       wp_enqueue_script('ncd-admin-base');
       wp_enqueue_script('ncd-ajax-handler');
   }

   private function register_styles($version) {
       wp_register_style(
           'ncd-admin-base', 
           NCD_ASSETS_URL . 'css/admin/base.css',
           [],
           $version
       );

       wp_register_style(
           'ncd-admin-tabs', 
           NCD_ASSETS_URL . 'css/admin/tabs.css',
           ['ncd-admin-base'],
           $version
       );

       wp_register_style(
           'ncd-admin-customers',
           NCD_ASSETS_URL . 'css/admin/customers.css',
           ['ncd-admin-base'],
           $version
       );

       wp_register_style(
           'ncd-admin-templates',
           NCD_ASSETS_URL . 'css/admin/templates.css',
           ['ncd-admin-base'],
           $version
       );

       wp_register_style(
           'ncd-admin-settings',
           NCD_ASSETS_URL . 'css/admin/settings.css',
           ['ncd-admin-base'],
           $version
       );

       wp_register_style(
           'ncd-admin-statistics',
           NCD_ASSETS_URL . 'css/admin/statistics.css',
           ['ncd-admin-base'],
           $version
       );

       // Base Styles aktivieren
       wp_enqueue_style('ncd-admin-base');
       wp_enqueue_style('ncd-admin-tabs');
       wp_enqueue_style('dashicons');
   }

   private function register_page_specific_assets($current_page, $version) {
       // Page Manager Scripts
       wp_register_script(
           'ncd-customer-manager',
           NCD_ASSETS_URL . 'js/modules/customer-manager.js',
           ['jquery', 'ncd-admin-base', 'ncd-ajax-handler'],
           $version,
           true
       );

       wp_register_script(
           'ncd-template-manager',
           NCD_ASSETS_URL . 'js/modules/template-manager.js',
           ['jquery', 'ncd-admin-base', 'ncd-ajax-handler'],
           $version,
           true
       );

       wp_register_script(
           'ncd-feedback-manager',
           NCD_ASSETS_URL . 'js/modules/feedback-manager.js',
           ['jquery', 'ncd-admin-base', 'ncd-ajax-handler'],
           $version,
           true
       );

       wp_register_script(
           'ncd-tab-manager',
           NCD_ASSETS_URL . 'js/modules/tab-manager.js',
           ['ncd-admin-base'],
           $version,
           true
       );

       // Template spezifische Lokalisierung
       if ($current_page === 'new-customers-templates') {
           wp_localize_script('ncd-template-manager', 'ncdTemplates', $this->get_template_localization());
       }

       // Tab spezifische Lokalisierung
       if ($this->needs_tabs($current_page)) {
           wp_localize_script('ncd-tab-manager', 'ncdTabs', $this->get_tab_localization());
       }
   }

   private function enqueue_required_scripts($current_page) {
       $dependencies = ['jquery', 'ncd-admin-base', 'ncd-ajax-handler'];

       if ($this->needs_tabs($current_page)) {
           $dependencies[] = 'ncd-tab-manager';
           wp_enqueue_script('ncd-tab-manager');
       }

       switch ($current_page) {
           case 'new-customers':
               wp_enqueue_script('ncd-customer-manager');
               wp_enqueue_style('ncd-admin-customers');
               $dependencies[] = 'ncd-customer-manager';
               break;

           case 'new-customers-templates':
               wp_enqueue_script('ncd-template-manager');
               wp_enqueue_style('ncd-admin-templates');
               $dependencies[] = 'ncd-template-manager';
               break;

           case 'new-customers-settings':
               wp_enqueue_script('ncd-feedback-manager');
               wp_enqueue_style('ncd-admin-settings');
               $dependencies[] = 'ncd-feedback-manager';
               break;

           case 'new-customers-statistics':
               wp_enqueue_style('ncd-admin-statistics');
               break;
       }

       // Haupt Admin Script
       wp_enqueue_script(
           'ncd-admin',
           NCD_ASSETS_URL . 'js/admin.js',
           $dependencies,
           WP_DEBUG ? time() : NCD_VERSION,
           true
       );
   }

   private function needs_tabs($current_page) {
       return in_array($current_page, [
           'new-customers-settings',
           'new-customers-templates'
       ]);
   }

   private function get_admin_localization() {
       return [
           'ajaxurl' => admin_url('admin-ajax.php'),
           'nonce' => wp_create_nonce('ncd-admin-nonce'),
           'debug' => WP_DEBUG,
           'currentPage' => isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '',
           'messages' => $this->get_admin_messages()
       ];
   }

   private function get_admin_messages() {
       return [
           'error' => __('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', 'newcustomer-discount'),
           'email_required' => __('Bitte geben Sie eine E-Mail-Adresse ein.', 'newcustomer-discount'),
           'confirm_test' => __('Möchten Sie eine Test-E-Mail an diese Adresse senden?', 'newcustomer-discount'),
           'confirm_send' => __('Möchten Sie einen Rabattcode senden?', 'newcustomer-discount'),
           'sending' => __('Sende...', 'newcustomer-discount'),
           'success' => __('Erfolgreich gespeichert.', 'newcustomer-discount'),
           'loading' => __('Laden...', 'newcustomer-discount'),
           'confirm_template_activation' => __('Möchten Sie dieses Template wirklich aktivieren?', 'newcustomer-discount'),
           'yes' => __('Ja', 'newcustomer-discount'), 
           'no' => __('Nein', 'newcustomer-discount'),
           'settings_saved' => __('Template-Einstellungen wurden gespeichert.', 'newcustomer-discount'),
           'feedback_required' => __('Bitte geben Sie Ihr Feedback ein.', 'newcustomer-discount'),
           'submit_feedback' => __('Feedback senden', 'newcustomer-discount'),
           'feedback_success' => __('Vielen Dank für Ihr Feedback!', 'newcustomer-discount'),
           'enter_email' => __('E-Mail Adresse eingeben', 'newcustomer-discount'),
           'send_test' => __('Test-E-Mail senden', 'newcustomer-discount')
       ];
   }

   private function get_template_localization() {
       return [
           'messages' => [
               'save_success' => __('Template-Einstellungen wurden gespeichert.', 'newcustomer-discount'),
               'save_error' => __('Fehler beim Speichern der Einstellungen.', 'newcustomer-discount'),
               'preview_error' => __('Fehler beim Generieren der Vorschau.', 'newcustomer-discount')
           ]
       ];
   }

   private function get_tab_localization() {
       return [
           'defaultTab' => 'logo-settings',
           'messages' => [
               'loading' => __('Laden...', 'newcustomer-discount'),
               'error' => __('Fehler beim Laden des Tabs', 'newcustomer-discount')
           ]
       ];
   }

   private function debug_loaded_assets() {
       global $wp_scripts, $wp_styles;
        
       error_log('Loaded NCD Admin Scripts:');
       foreach ($wp_scripts->queue as $handle) {
           if (strpos($handle, 'ncd-') !== false) {
               error_log(' - ' . $handle);
           }
       }
        
       error_log('Loaded NCD Admin Styles:');
       foreach ($wp_styles->queue as $handle) {
           if (strpos($handle, 'ncd-') !== false) {
               error_log(' - ' . $handle);
           }
       }
   }

   public function get_page($page_key) {
       return isset($this->pages[$page_key]) ? $this->pages[$page_key] : null;
   }

   public function get_ajax_handler() {
       return $this->ajax;
   }

   public function is_plugin_page($page_key) {
       $screen = get_current_screen();
       if (!$screen) {
           return false;
       }

       return strpos($screen->id, 'new-customers-' . $page_key) !== false;
   }
}