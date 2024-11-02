<?php
/**
 * Admin Base Class
 *
 * Grundlegende Admin-Funktionalität und Initialisierung
 *
 * @package NewCustomerDiscount
 * @subpackage Admin\Core
 * @since 0.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class NCD_Admin_Base {
    /**
     * Customer Tracker Instanz
     * 
     * @var NCD_Customer_Tracker
     */
    protected $customer_tracker;

    /**
     * Coupon Generator Instanz
     * 
     * @var NCD_Coupon_Generator
     */
    protected $coupon_generator;

    /**
     * Email Sender Instanz
     * 
     * @var NCD_Email_Sender
     */
    protected $email_sender;

    /**
     * Constructor
     */
    public function __construct() {
        if (WP_DEBUG) {
            error_log('Initializing NCD Admin Base');
        }

        $this->init_dependencies();
        $this->init_hooks();
    }

    /**
     * Initialisiert die Abhängigkeiten
     */
    protected function init_dependencies() {
        try {
            $this->customer_tracker = new NCD_Customer_Tracker();
            $this->coupon_generator = new NCD_Coupon_Generator();
            $this->email_sender = new NCD_Email_Sender();

            if (WP_DEBUG) {
                error_log('NCD Admin Base dependencies initialized successfully');
            }
        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('Error initializing NCD Admin Base dependencies: ' . $e->getMessage());
            }
        }
    }

    /**
     * Initialisiert die WordPress Hooks
     */
    protected function init_hooks() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
    }


    /**
     * Lädt die Admin Assets
     *
     * @param string $hook Der aktuelle Admin-Seiten-Hook
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'new-customers') === false) {
            return;
        }

        wp_enqueue_style(
            'ncd-admin',
            NCD_PLUGIN_URL . 'assets/css/admin.css',
            [],
            NCD_VERSION
        );

        wp_enqueue_script(
            'ncd-admin',
            NCD_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            NCD_VERSION,
            true
        );

        wp_localize_script('ncd-admin', 'ncdAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ncd-admin-nonce'),
            'messages' => [
                'confirm_send' => __('Möchten Sie wirklich einen Rabattcode an diesen Kunden senden?', 'newcustomer-discount'),
                'confirm_test' => __('Möchten Sie eine Test-E-Mail an diese Adresse senden?', 'newcustomer-discount'),
                'error' => __('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', 'newcustomer-discount'),
                'email_required' => __('Bitte geben Sie eine E-Mail-Adresse ein.', 'newcustomer-discount')
            ]
        ]);
    }

    /**
     * Zeigt Admin-Benachrichtigungen an
     */
    public function display_admin_notices() {
        settings_errors('ncd_messages');
    }

    /**
     * Fügt Admin-Benachrichtigung hinzu
     *
     * @param string $message Nachricht
     * @param string $type Typ der Nachricht (success, error, warning, info)
     */
    protected function add_admin_notice($message, $type = 'success') {
        add_settings_error(
            'ncd_messages',
            'ncd_message',
            $message,
            $type
        );
    }

    /**
     * Loggt Fehler für Debugging
     *
     * @param string $message Fehlermeldung
     * @param array $context Zusätzliche Kontext-Informationen
     */
    protected function log_error($message, $context = []) {
        if (WP_DEBUG) {
            error_log(sprintf(
                '[NewCustomerDiscount] Admin Base Error: %s | Context: %s',
                $message,
                json_encode($context)
            ));
        }
    }

    /**
     * Prüft die Admin-Berechtigung
     *
     * @return bool
     */
    protected function check_admin_permissions() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Sie haben nicht die erforderlichen Berechtigungen für diese Aktion.', 'newcustomer-discount'));
            return false;
        }
        return true;
    }

    /**
     * Gibt eine Instanz einer Admin-Komponente zurück
     *
     * @param string $component Name der Komponente
     * @return mixed|null
     */
    protected function get_component($component) {
        $components = [
            'customer_tracker' => $this->customer_tracker,
            'coupon_generator' => $this->coupon_generator,
            'email_sender' => $this->email_sender
        ];

        return isset($components[$component]) ? $components[$component] : null;
    }

    /**
     * Überprüft AJAX-Anfragen
     *
     * @param string $action Optional. Die Nonce-Action.
     * @param string $nonce_field Optional. Das Nonce-Feld.
     * @return bool
     */
    protected function check_ajax_request($action = 'ncd-admin-nonce', $nonce_field = 'nonce') {
        if (!check_ajax_referer($action, $nonce_field, false)) {
            wp_send_json_error([
                'message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'newcustomer-discount')
            ]);
            return false;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Keine Berechtigung.', 'newcustomer-discount')
            ]);
            return false;
        }

        return true;
    }

    /**
     * Sendet AJAX Erfolg
     *
     * @param string $message
     * @param array $data
     */
    protected function send_ajax_success($message, $data = []) {
        wp_send_json_success(array_merge(
            ['message' => $message],
            $data
        ));
    }

    /**
     * Sendet AJAX Fehler
     *
     * @param string $message
     * @param array $data
     */
    protected function send_ajax_error($message, $data = []) {
        wp_send_json_error(array_merge(
            ['message' => $message],
            $data
        ));
    }
}