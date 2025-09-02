<?php
/*
Plugin Name: GoWPTracker
Description: Server-side tracking of outbound clicks from pre-landing pages (PLP) to e-commerce, with logging and redirect.
Version: 0.1.0
Author: Nohow2117
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Main plugin class
class GoWPTracker {
    public function __construct() {
        add_action( 'init', [ $this, 'register_go_endpoint' ] );
    }

    public function register_go_endpoint() {
        add_rewrite_rule( '^go$', 'index.php?gowptracker_go=1', 'top' );
        add_rewrite_tag( '%gowptracker_go%', '1' );
        add_action( 'template_redirect', [ $this, 'handle_go_redirect' ] );
    }

    public function handle_go_redirect() {
        if ( get_query_var( 'gowptracker_go' ) ) {
            // Qui andrà la logica di validazione, logging e redirect
            wp_die( 'GoWPTracker: endpoint /go attivo. Implementazione in corso.' );
        }
    }
}

new GoWPTracker();
