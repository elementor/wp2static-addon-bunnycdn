<?php

namespace WP2StaticBunnyCDN;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Aws\BunnyCDN\BunnyCDNClient;
use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;
use Aws\Credentials\Credentials;

class Deployer {

    // prepare deploy, if modifies URL structure, should be an action
    // $this->prepareDeploy();

    // options - load from addon's static methods

    public function __construct() {}

    public function upload_files( string $processed_site_path ) : void {
        // check if dir exists
        if ( ! is_dir( $processed_site_path ) ) {
            return;
        }

        $client_options = [
            'profile' => Controller::getValue( 'bunnycdnProfile' ),
            'version' => 'latest',
            'region' => Controller::getValue( 'bunnycdnRegion' ),
        ];

        /*
            If no credentials option, SDK attempts to load credentials from
            your environment in the following order:

                 - environment variables.
                 - a credentials .ini file.
                 - an IAM role.
        */
        if (
            Controller::getValue( 'bunnycdnAccessKeyID' ) &&
            Controller::getValue( 'bunnycdnSecretAccessKey' )
        ) {
            $client_options['credentials'] = [
                'key' => Controller::getValue( 'bunnycdnAccessKeyID' ),
                'secret' => \WP2Static\CoreOptions::encrypt_decrypt(
                    'decrypt',
                    Controller::getValue( 'bunnycdnSecretAccessKey' )
                ),
            ];
            unset( $client_options['profile'] );
        }

        // instantiate BunnyCDN client
        $bunnycdn = new \Aws\BunnyCDN\BunnyCDNClient( $client_options );

        // iterate each file in ProcessedSite
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $processed_site_path,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ( $iterator as $filename => $file_object ) {
            $base_name = basename( $filename );
            if ( $base_name != '.' && $base_name != '..' ) {
                $real_filepath = realpath( $filename );

                // TODO: do filepaths differ when running from WP-CLI (non-chroot)?

                $cache_key = str_replace( $processed_site_path, '', $filename );

                if ( \WP2Static\DeployCache::fileisCached( $cache_key ) ) {
                    continue;
                }

                if ( ! $real_filepath ) {
                    $err = 'Trying to deploy unknown file to BunnyCDN: ' . $filename;
                    \WP2Static\WsLog::l( $err );
                    continue;
                }

                // Standardise all paths to use / (Windows support)
                $filename = str_replace( '\\', '/', $filename );

                if ( ! is_string( $filename ) ) {
                    continue;
                }

                $bunnycdn_key =
                    Controller::getValue( 'bunnycdnRemotePath' ) ?
                    Controller::getValue( 'bunnycdnRemotePath' ) . '/' .
                    ltrim( $cache_key, '/' ) :
                    ltrim( $cache_key, '/' );

                $mime_type = MimeTypes::GuessMimeType( $filename );

                $result = $bunnycdn->putObject(
                    [
                        'Bucket' => Controller::getValue( 'bunnycdnBucket' ),
                        'Key' => $bunnycdn_key,
                        'Body' => file_get_contents( $filename ),
                        'ACL'    => 'public-read',
                        'ContentType' => $mime_type,
                    ]
                );

                if ( $result['@metadata']['statusCode'] === 200 ) {
                    \WP2Static\DeployCache::addFile( $cache_key );
                }
            }
        }
    }


    public function cloudfront_invalidate_all_items() : void {
        if ( ! Controller::getValue( 'cfDistributionID' ) ) {
            return;
        }

        \WP2Static\WsLog::l( 'Invalidating all CloudFront items' );

        /*
            If no credentials option, SDK attempts to load credentials from
            your environment in the following order:

                 - environment variables.
                 - a credentials .ini file.
                 - an IAM role.
        */
        if (
            Controller::getValue( 'bunnycdnAccessKeyID' ) &&
            Controller::getValue( 'bunnycdnSecretAccessKey' )
        ) {

            $credentials = new \Aws\Credentials\Credentials(
                Controller::getValue( 'bunnycdnAccessKeyID' ),
                \WP2Static\CoreOptions::encrypt_decrypt(
                    'decrypt',
                    Controller::getValue( 'bunnycdnSecretAccessKey' )
                )
            );
        }

        $client = \Aws\CloudFront\CloudFrontClient::factory(
            [
                'profile' => Controller::getValue( 'cfProfile' ),
                'region' => Controller::getValue( 'cfRegion' ),
                'version' => 'latest',
                'credentials' => isset( $credentials ) ? $credentials : '',
            ]
        );

        try {
            $result = $client->createInvalidation(
                [
                    'DistributionId' => Controller::getValue( 'cfDistributionID' ),
                    'InvalidationBatch' => [
                        'CallerReference' => 'WP2Static BunnyCDN Add-on ' . time(),
                        'Paths' => [
                            'Items' => [ '/*' ],
                            'Quantity' => 1,
                        ],
                    ],
                ]
            );

        } catch ( AwsException $e ) {
            // output error message if fails
            error_log( $e->getMessage() );
        }
    }
}

