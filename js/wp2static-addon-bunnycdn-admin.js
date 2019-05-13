(function( $ ) {
	'use strict';

	$(function() {
    deploy_options['bunnycdn'] = {
        exportSteps: [
            'bunnycdn_prepare_export',
            'bunnycdn_transfer_files',
            'bunnycdn_purge_cache',
            'finalize_deployment'
        ],
        required_fields: {
          bunnycdnPullZoneName: 'Please specify your BunnyCDN pull zone name in order to deploy to BunnyCDN.',
          bunnycdnAPIKey: 'Please specify your BunnyCDN API/FTP password in order to deploy to BunnyCDN.',
        }
    };

    status_descriptions['bunnycdn_prepare_export'] = 'Preparing files for BunnyCDN deployment';
    status_descriptions['bunnycdn_transfer_files'] = 'Deploying files via BunnyCDN';
    status_descriptions['bunnycdn_purge_cache'] = 'Purging BunnyCDN cache';
  }); // end DOM ready

})( jQuery );
