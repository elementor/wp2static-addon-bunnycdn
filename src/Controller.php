<?php

namespace WP2StaticBunnyCDN;

class Controller {
    public function run() : void {
        add_filter( 'wp2static_add_menu_items', [ 'WP2StaticBunnyCDN\Controller', 'addSubmenuPage' ] );

        add_action(
            'admin_post_wp2static_bunnycdn_save_options',
            [ $this, 'saveOptionsFromUI' ],
            15,
            1
        );

        add_action(
            'wp2static_deploy',
            [ $this, 'deploy' ],
            15,
            1
        );

        add_action(
            'wp2static_post_deploy_trigger',
            [ 'WP2StaticBunnyCDN\BunnyCDN', 'bunnycdn_purge_cache' ],
            15,
            1
        );

        if ( defined( 'WP_CLI' ) ) {
            \WP_CLI::add_command(
                'wp2static bunnycdn',
                [ 'WP2StaticBunnyCDN\CLI', 'bunnycdn' ]
            );
        }
    }

    /**
     *  Get all add-on options
     *
     *  @return mixed[] All options
     */
    public static function getOptions() : array {
        global $wpdb;
        $options = [];

        $table_name = $wpdb->prefix . 'wp2static_addon_bunnycdn_options';

        $rows = $wpdb->get_results( "SELECT * FROM $table_name" );

        foreach ( $rows as $row ) {
            $options[ $row->name ] = $row;
        }

        return $options;
    }

    /**
     * Seed options
     */
    public static function seedOptions() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_bunnycdn_options';

        $query_string =
            "INSERT INTO $table_name (name, value, label, description) VALUES (%s, %s, %s, %s);";

        $query = $wpdb->prepare(
            $query_string,
            'bunnycdnAccountAPIKey',
            '',
            'Account API Key',
            ''
        );

        $wpdb->query( $query );

        $query = $wpdb->prepare(
            $query_string,
            'bunnycdnStorageZoneName',
            '',
            'Storage Zone Name',
            ''
        );

        $wpdb->query( $query );
    }

    /**
     * Save options
     *
     * @param mixed $value option value to save
     */
    public static function saveOption( string $name, $value ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_bunnycdn_options';

        $wpdb->update(
            $table_name,
            [ 'value' => $value ],
            [ 'name' => $name ]
        );
    }

    public static function renderBunnyCDNPage() : void {
        $view = [];
        $view['nonce_action'] = 'wp2static-bunnycdn-options';
        $view['uploads_path'] = \WP2Static\SiteInfo::getPath( 'uploads' );
        $bunnycdn_path = \WP2Static\SiteInfo::getPath( 'uploads' ) . 'wp2static-processed-site.bunnycdn';

        $view['options'] = self::getOptions();

        $view['bunnycdn_url'] =
            is_file( $bunnycdn_path ) ?
                \WP2Static\SiteInfo::getUrl( 'uploads' ) . 'wp2static-processed-site.bunnycdn' : '#';

        require_once __DIR__ . '/../views/bunnycdn-page.php';
    }


    public function deploy( string $processed_site_path ) : void {
        \WP2Static\WsLog::l( 'BunnyCDN Addon deploying' );

        $bunnycdn_deployer = new BunnyCDN();
        $bunnycdn_deployer->upload_files( $processed_site_path );
    }

    public static function activate_for_single_site() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_bunnycdn_options';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            value VARCHAR(255) NOT NULL,
            label VARCHAR(255) NULL,
            description VARCHAR(255) NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        $options = self::getOptions();

        if ( ! isset( $options['bunnycdnBucket'] ) ) {
            self::seedOptions();
        }

        do_action(
            'wp2static_register_addon',
            'wp2static-addon-bunnycdn',
            'deploy',
            'BunnyCDN Deployment',
            'https://wp2static.com/addons/bunnycdn/',
            'Deploys to BunnyCDN with cache invalidation'
        );
    }

    public static function deactivate_for_single_site() : void {
    }

    public static function deactivate( bool $network_wide = null ) : void {
        if ( $network_wide ) {
            global $wpdb;

            $query = 'SELECT blog_id FROM %s WHERE site_id = %d;';

            $site_ids = $wpdb->get_col(
                sprintf(
                    $query,
                    $wpdb->blogs,
                    $wpdb->siteid
                )
            );

            foreach ( $site_ids as $site_id ) {
                switch_to_blog( $site_id );
                self::deactivate_for_single_site();
            }

            restore_current_blog();
        } else {
            self::deactivate_for_single_site();
        }
    }

    public static function activate( bool $network_wide = null ) : void {
        if ( $network_wide ) {
            global $wpdb;

            $query = 'SELECT blog_id FROM %s WHERE site_id = %d;';

            $site_ids = $wpdb->get_col(
                sprintf(
                    $query,
                    $wpdb->blogs,
                    $wpdb->siteid
                )
            );

            foreach ( $site_ids as $site_id ) {
                switch_to_blog( $site_id );
                self::activate_for_single_site();
            }

            restore_current_blog();
        } else {
            self::activate_for_single_site();
        }
    }

    /**
     * Add WP2Static submenu
     *
     * @param mixed[] $submenu_pages array of submenu pages
     * @return mixed[] array of submenu pages
     */
    public static function addSubmenuPage( array $submenu_pages ) : array {
        $submenu_pages['bunnycdn'] = [ 'WP2StaticBunnyCDN\Controller', 'renderBunnyCDNPage' ];

        return $submenu_pages;
    }

    public static function saveOptionsFromUI() : void {
        check_admin_referer( 'wp2static-bunnycdn-options' );

        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_bunnycdn_options';

        $accountAPIKey =
            $_POST['bunnycdnAccountAPIKey'] ?
            \WP2Static\CoreOptions::encrypt_decrypt(
                'encrypt',
                sanitize_text_field( $_POST['bunnycdnAccountAPIKey'] )
            ) : '';

        $wpdb->update(
            $table_name,
            [ 'value' => $accountAPIKey ],
            [ 'name' => 'bunnycdnAccountAPIKey' ]
        );

        $wpdb->update(
            $table_name,
            [ 'value' => sanitize_text_field( $_POST['bunnycdnStorageZoneName'] ) ],
            [ 'name' => 'bunnycdnStorageZoneName' ]
        );

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-bunnycdn' ) );
        exit;
    }

    /**
     * Get option value
     *
     * @return string option value
     */
    public static function getValue( string $name ) : string {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_bunnycdn_options';

        $sql = $wpdb->prepare(
            "SELECT value FROM $table_name WHERE" . ' name = %s LIMIT 1',
            $name
        );

        $option_value = $wpdb->get_var( $sql );

        if ( ! is_string( $option_value ) ) {
            return '';
        }

        return $option_value;
    }
}

