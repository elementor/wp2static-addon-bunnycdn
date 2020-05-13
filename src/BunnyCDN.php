<?php

namespace WP2StaticBunnyCDN;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use GuzzleHttp\Client;

class BunnyCDN {

    private $accountAPIKey;
    private $storageZoneName;
    private $storageZoneAccessKey;
    private $pullZoneID;

    public function __construct() {
        $this->storageZoneAccessKey = '';
        $this->pullZoneID = 0;
        $this->accountAPIKey = \WP2Static\CoreOptions::encrypt_decrypt(
            'decrypt',
            Controller::getValue( 'bunnycdnAccountAPIKey' )
        );
        $this->storageZoneName = Controller::getValue( 'bunnycdnStorageZoneName' );

        if ( ! $this->accountAPIKey || ! $this->storageZoneName ) {
            $err = 'Unable to connect to BunnyCDN API without ' .
            'Account API Key, Storage Zone Name, Storage Zone Access Key & Pull Zone ID';
            \WP2Static\WsLog::l( $err );
        }

        $this->accountClient = new Client( [ 'base_uri' => 'https://bunnycdn.com' ] );

        $this->accountHeaders = [ 'AccessKey' =>  $this->accountAPIKey ];

        // get list of Storage Zones to find ID
        // get Storage Zone Access Key using Account API Key
        $res = $this->accountClient->request(
            'GET',
            "api/storagezone",
            [
                'headers' => $this->accountHeaders,
            ],
        );

        $result = json_decode( (string) $res->getBody() );

        if ( $result ) {
            foreach ( $result as $storageZone ) {
                if ( $storageZone->Name === $this->storageZoneName ) {
                    // validate if pull zone is connected
                    if ( ! $storageZone->PullZones ) {
                        $err = 'No Pull Zone found attached to this Storage Zone, please check.';
                        \WP2Static\WsLog::l( $err );
                        error_log($err);
                    }

                    if ( count( $storageZone->PullZones ) > 1 ) {
                        $notice = 'Multiple Pull Zones attached to Storage Zone, using first.';
                        \WP2Static\WsLog::l( $notice );
                        error_log($notice);
                    }

                    // use first connected PullZone ID
                    $this->pullZoneID = $storageZone->PullZones[0]->Id;
                    $notice = "Using Pull Zone ID $this->pullZoneID.";
                    \WP2Static\WsLog::l( $notice );
                    error_log( $notice );

                    $this->storageZoneAccessKey = $storageZone->Password;
                }
            }
        }

        if ( ! $this->storageZoneAccessKey ) {
            $err = 'Unable to find Storage Zone by name, please check your input.';
            \WP2Static\WsLog::l( $err );
            error_log($err);
        }

        $this->storageZoneclient = new Client( [ 'base_uri' => 'https://storage.bunnycdn.com' ] );

        $this->storageZoneheaders = [
            'AccessKey' => $this->storageZoneAccessKey,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Delete all files and directories in Storage Zone
     *
     * Will delete directories without needing to drill down into them
     *
     */ 
    public function delete_storage_zone_files() : bool {
        $storage_zone_files = $this->list_storage_zone_files();

        foreach ( $storage_zone_files as $file ) {
            $res = $this->storageZoneclient->request(
                'DELETE',
                "$this->storageZoneName/$file",
                [
                    'headers' => $this->storageZoneheaders,
                ],
            );

            $result = json_decode( (string) $res->getBody() );

            if ( ! $result ) {
                return false;
            }
        }
        
        return true; 
    }

    /**
     * List all files within Storage Zone
     *
     * TODO: write Iterator to get nested files
     *
     * @return string[] list of files
     */
    public function list_storage_zone_files() : array{
        $storage_zone_files = [];

        $res = $this->storageZoneclient->request(
            'GET',
            "$this->storageZoneName/",
            [
                'headers' => $this->storageZoneheaders,
            ],
        );

        $result = json_decode( (string) $res->getBody() );

        if ( $result ) {
            foreach ( $result as $path ) {
                if ( $path->IsDirectory ) {
                    $storage_zone_files[] = $path->ObjectName . "/";
                } else {
                    $storage_zone_files[] = $path->ObjectName;
                }
            } 
        }
        
        return $storage_zone_files; 
    }

    public function upload_files( string $processed_site_path ) : void {
        if ( ! is_dir( $processed_site_path ) ) {
            return;
        }

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
                // TODO: apply WP method of get_safe_path or such
                $filename = str_replace( '\\', '/', $filename );

                if ( ! is_string( $filename ) ) {
                    continue;
                }

                $remote_path = ltrim( $cache_key, '/' );

                $res = $this->storageZoneclient->request(
                    'PUT',
                    "$this->storageZoneName/$remote_path",
                    [
                        'headers' => $this->storageZoneheaders,
                        'body' => file_get_contents( $filename ),
                    ],
                );

                $result = json_decode( (string) $res->getBody() );

                if ( $result ) {
                    error_log(print_r($result, true));
                   
                    // TODO: Look for 201 status from Bunny 
                    // if ( $result['@metadata']['statusCode'] === 200 ) {
                    //     \WP2Static\DeployCache::addFile( $cache_key );
                    // }

                    // TODO: purge cache on each of these or on hook? 
                }
            }
        }
    }

    public static function bunnycdn_purge_cache( string $enabled_deployer ) : void {
        if ( $enabled_deployer !== 'wp2static-addon-bunnycdn' ) {
            return;
        }

        error_log('calling cache purge');

        // try {
        //     $endpoint = 'https://bunnycdn.com/api/pullzone/' .
        //         $this->pull_zone_id . '/purgeCache';

        //     $ch = curl_init();

        //     curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
        //     curl_setopt( $ch, CURLOPT_URL, $endpoint );
        //     curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        //     curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
        //     curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
        //     curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
        //     curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
        //     curl_setopt( $ch, CURLOPT_POST, 1 );

        //     curl_setopt(
        //         $ch,
        //         CURLOPT_HTTPHEADER,
        //         array(
        //             'Content-Type: application/json',
        //             'Content-Length: 0',
        //             'AccessKey: ' .
        //                 $this->pull_zone_access_key,
        //         )
        //     );

        //     $output = curl_exec( $ch );
        //     $status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

        //     curl_close( $ch );

        //     $good_response_codes = array( '100', '200', '201', '302' );

        //     if ( ! in_array( $status_code, $good_response_codes ) ) {
        //         $err =
        //             'BAD RESPONSE DURING BUNNYCDN PURGE CACHE: ' . $status_code;
        //         WsLog::l( $err );
        //         throw new Exception( $err );

        //         echo 'FAIL';
        //     }

        //     if ( ! defined( 'WP_CLI' ) ) {
        //         echo 'SUCCESS';
        //     }
        // } catch ( Exception $e ) {
        //     WsLog::l( 'BUNNYCDN EXPORT: error encountered' );
        //     WsLog::l( $e );
        // }
    }
}
