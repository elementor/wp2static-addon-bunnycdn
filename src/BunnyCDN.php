<?php

namespace WP2Static;

class BunnyCDN extends SitePublisher {

    public function __construct() {
        $plugin = Controller::getInstance();

        $this->api_base = 'https://storage.bunnycdn.com';
        $this->batch_size = $plugin->options->getOption('deployBatchSize');
        $this->storage_zone_name = $plugin->options->getOption('bunnycdnStorageZoneName');
        $this->storage_zone_access_key = $plugin->options->getOption('bunnycdnStorageZoneAccessKey');
        $this->pull_zone_access_key = $plugin->options->getOption('bunnycdnPullZoneAccessKey');
        $this->pull_zone_id = $plugin->options->getOption('bunnycdnPullZoneID');
        $this->cdn_remote_path = $plugin->options->getOption('bunnycdnRemotePath');
        $this->previous_hashes_path =
            $plugin->options->getOption('wp_uploads_path') .
                '/WP2STATIC-BUNNYCDN-PREVIOUS-HASHES.txt';
    }

    public function bunnycdn_transfer_files() {
        $this->files_remaining = $this->getRemainingItemsCount();

        if ( $this->files_remaining < 0 ) {
            echo 'ERROR';
            die(); }

        if ( $this->batch_size > $this->files_remaining ) {
            $this->batch_size = $this->files_remaining;
        }

        $lines = $this->getItemsToDeploy( $this->batch_size );

        $this->openPreviousHashesFile();

        foreach ( $lines as $line ) {
            list($this->local_file, $this->target_path) = explode( ',', $line );

            $this->local_file = $this->archive->path . $this->local_file;

            if ( ! is_file( $this->local_file ) ) {
                continue; }

            if ( isset( $this->settings['bunnycdnRemotePath'] ) ) {
                $this->target_path =
                    $this->settings['bunnycdnRemotePath'] . '/' .
                        $this->target_path;
            }

            $this->local_file_contents = file_get_contents( $this->local_file );

            $this->hash_key =
                $this->target_path . basename( $this->local_file );

                if ( isset( $this->file_paths_and_hashes[ $this->hash_key ] ) ) {
                    $prev = $this->file_paths_and_hashes[ $this->hash_key ];
                    $current = crc32( $this->local_file_contents );

                if ( $prev != $current ) {
                    if ( $this->fileExistsInBunnyCDN() ) {
                        $this->updateFileInBunnyCDN();
                    } else {
                        $this->createFileInBunnyCDN();
                    }

                    $this->recordFilePathAndHashInMemory(
                        $this->hash_key,
                        $this->local_file_contents
                    );
                }
            } else {
                if ( $this->fileExistsInBunnyCDN() ) {
                    $this->updateFileInBunnyCDN();
                } else {
                    $this->createFileInBunnyCDN();
                }

                $this->recordFilePathAndHashInMemory(
                    $this->hash_key,
                    $this->local_file_contents
                );
            }
        }

        unset( $this->bunnycdn );

        $this->writeFilePathAndHashesToFile();

        $this->pauseBetweenAPICalls();

        if ( $this->uploadsCompleted() ) {
            $this->finalizeDeployment();
        }
    }

    public function bunnycdn_purge_cache() {
        try {
            $endpoint = 'https://bunnycdn.com/api/pullzone/' .
                $this->settings['bunnycdnPullZoneID'] . '/purgeCache';

            $ch = curl_init();

            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
            curl_setopt( $ch, CURLOPT_URL, $endpoint );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
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
                        $this->settings['bunnycdnPullZoneAccessKey'],
                )
            );

            $output = curl_exec( $ch );
            $status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

            curl_close( $ch );

            $good_response_codes = array( '100', '200', '201' );

            if ( ! in_array( $status_code, $good_response_codes ) ) {
                WsLog::l(
                    'BAD RESPONSE STATUS (' . $status_code . '): '
                );

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
                $this->settings['bunnycdnStorageZoneName'] .
                '/tmpFile';

            $ch = curl_init();

            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PUT' );
            curl_setopt( $ch, CURLOPT_URL, $remote_path );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
            curl_setopt( $ch, CURLOPT_HEADER, 0 );
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
            curl_setopt( $ch, CURLOPT_POST, 1 );

            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    'AccessKey: ' .
                        $this->settings['bunnycdnStorageZoneAccessKey'],
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

            $good_response_codes = array( '100', '200', '201', '301', '302', '304' );

            if ( ! in_array( $status_code, $good_response_codes ) ) {
                WsLog::l(
                    'BAD RESPONSE STATUS (' . $status_code . '): '
                );
            }
        } catch ( Exception $e ) {
            WsLog::l( 'BUNNYCDN EXPORT: error encountered' );
            WsLog::l( $e );
        }

        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS';
        }
    }

    public function fileExistsInBunnyCDN() {
        $this->client = new Request();

        return false;
    }

    public function createFileInBunnyCDN() {
        try {
            $remote_path = $this->api_base . '/' .
                $this->settings['bunnycdnStorageZoneName'] .
                '/' . $this->target_path;

            $headers = array(
                'AccessKey: ' .
                    $this->settings['bunnycdnStorageZoneAccessKey'],
            );

            $this->client->putWithFileStreamAndHeaders(
                $remote_path,
                $this->local_file,
                $headers
            );

            $this->checkForValidResponses(
                $this->client->status_code,
                array( '100', '200', '201', '301', '302', '304' )
            );
        } catch ( Exception $e ) {
            $this->handleException( $e );
        }
    }
}

$bunny = new BunnyCDN();
