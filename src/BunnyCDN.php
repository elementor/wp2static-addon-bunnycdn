<?php

namespace WP2StaticBunnyCDN;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use GuzzleHttp\Client;

class BunnyCDN {

    public $accountAPIKey;
    public $storageZoneName;
    public $storageZoneAccessKey;
    public $pullZoneID;

    public function __construct() {
        $this->accountAPIKey = \WP2Static\CoreOptions::encrypt_decrypt(
            'decrypt',
            Controller::getValue( 'bunnycdnAccountAPIKey' )
        );
        $this->storageZoneName = Controller::getValue( 'bunnycdnStorageZoneName' );
        $this->storageZoneAccessKey = \WP2Static\CoreOptions::encrypt_decrypt(
            'decrypt',
            Controller::getValue( 'bunnycdnStorageZoneAccessKey' )
        );

        $this->pullZoneID = Controller::getValue( 'bunnycdnPullZoneID' );

        if (
            ! $this->accountAPIKey ||
            ! $this->storageZoneName ||
            ! $this->storageZoneAccessKey ||
            ! $this->pullZoneID
        ) {
            $err = 'Unable to connect to BunnyCDN API without ' .
            'Account API Key, Storage Zone Name, Storage Zone Access Key & Pull Zone ID';
            \WP2Static\WsLog::l( $err );
        }

        $this->storageZoneclient = new Client( [ 'base_uri' => 'https://storage.bunnycdn.com' ] );

        $this->storageZoneheaders = [
            'AccessKey' => $this->storageZoneAccessKey,
            'Accept' => 'application/json',
        ];

        $this->pullZoneclient = new Client( [ 'base_uri' => 'https://bunnycdn.com/api' ] );

        $this->pullZoneheaders = [ 'AccessKey' => 'Bearer ' . $this->storageZoneAccessKey ];
    }

    /**
     * List all files within Storage Zone
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
            $storage_zone_files = array_map(function ($file) { return $file->ObjectName; }, $result);
        }
        
        return $storage_zone_files; 
    }

    public function bunnycdn_transfer_files() {
        $this->client = new Request();

        $lines = $this->getItemsToDeploy( $this->batch_size );

        foreach ( $lines as $local_file => $target_path ) {
            $abs_local_file = SiteInfo::getPath( 'uploads' ) .
                'wp2static-exported-site/' .
                $local_file;

            if ( ! is_file( $abs_local_file ) ) {
                $err = 'COULDN\'T FIND LOCAL FILE TO DEPLOY: ' .
                    $this->local_file;
                WsLog::l( $err );
                throw new Exception( $err );
            }

            if ( isset( $this->cdn_remote_path ) ) {
                $target_path =
                    $this->cdn_remote_path . '/' .
                        $target_path;
            }

            if ( ! DeployCache::fileIsCached( $abs_local_file ) ) {
                $this->createFileInBunnyCDN( $abs_local_file, $target_path );

                DeployCache::addFile( $abs_local_file );
            }

            DeployQueue::remove( $local_file );
        }

        $this->pauseBetweenAPICalls();

        if ( $this->uploadsCompleted() ) {
            $this->finalizeDeployment();
        }
    }

    public function bunnycdn_purge_cache() {
        try {
            $endpoint = 'https://bunnycdn.com/api/pullzone/' .
                $this->pull_zone_id . '/purgeCache';

            $ch = curl_init();

            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
            curl_setopt( $ch, CURLOPT_URL, $endpoint );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
            curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
            curl_setopt( $ch, CURLOPT_POST, 1 );

            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    'Content-Type: application/json',
                    'Content-Length: 0',
                    'AccessKey: ' .
                        $this->pull_zone_access_key,
                )
            );

            $output = curl_exec( $ch );
            $status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

            curl_close( $ch );

            $good_response_codes = array( '100', '200', '201', '302' );

            if ( ! in_array( $status_code, $good_response_codes ) ) {
                $err =
                    'BAD RESPONSE DURING BUNNYCDN PURGE CACHE: ' . $status_code;
                WsLog::l( $err );
                throw new Exception( $err );

                echo 'FAIL';
            }

            if ( ! defined( 'WP_CLI' ) ) {
                echo 'SUCCESS';
            }
        } catch ( Exception $e ) {
            WsLog::l( 'BUNNYCDN EXPORT: error encountered' );
            WsLog::l( $e );
        }
    }

    public function test_bunnycdn() {
        try {
            $remote_path = $this->api_base . '/' .
                $this->storage_zone_name .
                '/tmpFile';

            $ch = curl_init();

            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PUT' );
            curl_setopt( $ch, CURLOPT_URL, $remote_path );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
            curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
            curl_setopt( $ch, CURLOPT_HEADER, 0 );
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
            curl_setopt( $ch, CURLOPT_POST, 1 );

            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    'AccessKey: ' .
                        $this->storage_zone_access_key,
                )
            );

            $post_options = array(
                'body' => 'Test WP2Static connectivity',
            );

            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                $post_options
            );

            $output = curl_exec( $ch );
            $status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

            curl_close( $ch );

            $good_response_codes =
                array( '100', '200', '201', '301', '302', '304' );

            if ( ! in_array( $status_code, $good_response_codes ) ) {
                $err =
                    'BAD RESPONSE DURING BUNNYCDN TEST DEPLOY: ' . $status_code;
                WsLog::l( $err );
                throw new Exception( $err );
            }
        } catch ( Exception $e ) {
            WsLog::l( 'BUNNYCDN EXPORT: error encountered' );
            WsLog::l( $e );
        }

        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS';
        }
    }

    public function createFileInBunnyCDN(
        string $local_file,
        string $target_path
    ) : void {
        $remote_path = $this->api_base . '/' .
            $this->storage_zone_name .
            '/' . $target_path;

        $headers = array( 'AccessKey: ' . $this->storage_zone_access_key );

        $this->client->putWithFileStreamAndHeaders(
            $remote_path,
            $local_file,
            $headers
        );

        $success = $this->checkForValidResponses(
            $this->client->status_code,
            array( '100', '200', '201', '301', '302', '304' )
        );

        if ( ! $success ) {
            $err = "Received {$this->client->status_code} response from API " .
               "with body: {$this->client->body}";
            WsLog::l( $err );

            http_response_code( $this->client->status_code );
            throw new Exception( $err );
        }
    }
}
