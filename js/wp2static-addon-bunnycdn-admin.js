(function( $ ) {
	'use strict';

	$(function() {
    WP2Static.deployOptions['bunnycdn'] = {
        exportSteps: [
            'bunnycdn_prepare_export',
            'bunnycdn_transfer_files',
            'bunnycdn_purge_cache',
            'finalize_deployment'
        ],
        requiredFields: {
          bunnycdnPullZoneName: 'Please specify your BunnyCDN pull zone name in order to deploy to BunnyCDN.',
          bunnycdnAPIKey: 'Please specify your BunnyCDN API/FTP password in order to deploy to BunnyCDN.',
        }
    };

    WP2Static.statusDescriptions['bunnycdn_prepare_export'] = 'Preparing files for BunnyCDN deployment';
    WP2Static.statusDescriptions['bunnycdn_transfer_files'] = 'Deploying files via BunnyCDN';
    WP2Static.statusDescriptions['bunnycdn_purge_cache'] = 'Purging BunnyCDN cache';
  }); // end DOM ready

})( jQuery );
