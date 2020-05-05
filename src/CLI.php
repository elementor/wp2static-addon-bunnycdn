<?php

namespace WP2StaticBunnyCDN;

use WP_CLI;


/**
 * WP2StaticBunnyCDN WP-CLI commands
 *
 * Registers WP-CLI commands for WP2StaticBunnyCDN under main wp2static cmd
 *
 * Usage: wp wp2static options set bunnycdnBucket mybucketname
 */
class CLI {

    /**
     * BunnyCDN commands
     *
     * @param string[] $args CLI args
     * @param string[] $assoc_args CLI args
     */
    public function bunnycdn(
        array $args,
        array $assoc_args
    ) : void {
        $action = isset( $args[0] ) ? $args[0] : null;

        if ( empty( $action ) ) {
            WP_CLI::error( 'Missing required argument: <options>' );
        }

        if ( $action === 'options' ) {
            WP_CLI::line( 'TBC setting options for BunnyCDN addon' );
        }
    }
}

