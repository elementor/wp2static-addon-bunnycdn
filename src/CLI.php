<?php

namespace WP2StaticBunnyCDN;

use WP_CLI;

/**
 * WP2StaticBunnyCDN WP-CLI commands
 *
 * Registers WP-CLI commands for WP2StaticBunnyCDN under main wp2static cmd
 *
 * Usage: wp wp2static options set bunnycdnPullZoneID mypullzoneid
 */
class CLI {

    /**
     * BunnyCDN add-on commands
     *
     * @param string[] $args CLI args
     * @param string[] $assoc_args CLI args
     */
    public function bunnycdn(
        array $args,
        array $assoc_args
    ) : void {
        $action = isset( $args[0] ) ? $args[0] : null;
        $arg = isset( $args[1] ) ? $args[1] : null;

        if ( empty( $action ) ) {
            WP_CLI::error( 'Missing required argument: <options|storage_zone_files>' );
        }

        if ( $action === 'options' ) {
            if ( empty( $arg ) ) {
                WP_CLI::error( 'Missing required argument: <get|set|list>' );
            }

            $option_name = isset( $args[2] ) ? $args[2] : null;

            if ( $arg === 'get' ) {
                if ( empty( $option_name ) ) {
                    WP_CLI::error( 'Missing required argument: <option-name>' );
                    return;
                }

                // decrypt apiToken
                if (
                    $option_name === 'bunnycdnAccountAPIKey' ||
                    $option_name === 'bunnycdnStorageZoneAccessKey'
                ) {
                    $option_value = \WP2Static\CoreOptions::encrypt_decrypt(
                        'decrypt',
                        Controller::getValue( $option_name )
                    );
                } else {
                    $option_value = Controller::getValue( $option_name );
                }

                WP_CLI::line( $option_value );
            }

            if ( $arg === 'set' ) {
                if ( empty( $option_name ) ) {
                    WP_CLI::error( 'Missing required argument: <option-name>' );
                    return;
                }

                $option_value = isset( $args[3] ) ? $args[3] : null;

                if ( empty( $option_value ) ) {
                    $option_value = '';
                }

                // decrypt apiToken
                if (
                    $option_name === 'bunnycdnAccountAPIKey' ||
                    $option_name === 'bunnycdnStorageZoneAccessKey'
                ) {
                    $option_value = \WP2Static\CoreOptions::encrypt_decrypt(
                        'encrypt',
                        $option_value
                    );
                }

                Controller::saveOption( $option_name, $option_value );
            }

            if ( $arg === 'list' ) {
                $options = Controller::getOptions();

                // decrypt encrypted values
                $options['bunnycdnAccountAPIKey']->value = \WP2Static\CoreOptions::encrypt_decrypt(
                    'decrypt',
                    $options['bunnycdnAccountAPIKey']->value
                );

                $options['bunnycdnStorageZoneAccessKey']->value = \WP2Static\CoreOptions::encrypt_decrypt(
                    'decrypt',
                    $options['bunnycdnStorageZoneAccessKey']->value
                );

                WP_CLI\Utils\format_items(
                    'table',
                    $options,
                    [ 'name', 'value' ]
                );
            }
        }

        if ( $action === 'storage_zone_files' ) {
            if ( empty( $arg ) ) {
                WP_CLI::error( 'Missing required argument: <list|count|delete>' );
            }

            if ( $arg === 'list' ) {
                $client = new BunnyCDN();

                $filenames = $client->list_storage_zone_files();

                foreach ( $filenames as $name ) {
                    WP_CLI::line( $name );
                }
            }

            if ( $arg === 'count' ) {
                $client = new BunnyCDN();

                $filenames = $client->list_storage_zone_files();

                WP_CLI::line( (string) count( $filenames ) );
            }

            if ( $arg === 'delete' ) {
                if ( ! isset( $assoc_args['force'] ) ) {
                    $this->multilinePrint(
                        "no --force given. Please type 'yes' to confirm
                        deletion of all keys in namespace"
                    );

                    $userval = trim( (string) fgets( STDIN ) );

                    if ( $userval !== 'yes' ) {
                        WP_CLI::error( 'Failed to delete namespace keys' );
                    }
                }

                $client = new BunnyCDN();

                $success = $client->delete_storage_zone_files();

                if ( ! $success ) {
                    WP_CLI::error( 'Failed to delete files in Storage Zone (maybe there weren\'t any?' );
                }

                WP_CLI::success( 'Deleted all files in Storage Zone' );
            }
        }
    }

    /**
     * Print multilines of input text via WP-CLI
     */
    public function multilinePrint( string $string ) : void {
        $msg = trim( str_replace( [ "\r", "\n" ], '', $string ) );

        $msg = preg_replace( '!\s+!', ' ', $msg );

        WP_CLI::line( PHP_EOL . $msg . PHP_EOL );
    }
}

