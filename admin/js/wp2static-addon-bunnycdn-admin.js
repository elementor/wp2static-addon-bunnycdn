(function( $ ) {
	'use strict';

	$(function() {
    deploy_options['bunnycdn'] = {
        exportSteps: [
            'bunnycdn_prepare_export',
            'bunnycdn_upload_files',
            'finalize_deployment'
        ],
        required_fields: {
          ghToken: 'Please specify your BunnyCDN personal access token in order to deploy to BunnyCDN.',
          ghRepo: 'Please specify your BunnyCDN repository name in order to deploy to BunnyCDN.',
          ghBranch: 'Please specify which branch in your BunnyCDN repository you want to deploy to.',
        },
        repo_field: {
          field: 'ghRepo',
          message: "Please ensure your BunnyCDN repo is specified as USER_OR_ORG_NAME/REPO_NAME\n"
        }
    };

    status_descriptions['bunnycdn_prepare_export'] = 'Preparing files for BunnyCDN deployment';
    status_descriptions['bunnycdn_upload_files'] = 'Deploying files via BunnyCDN';
    status_descriptions['cloudfront_invalidate_all_items'] = 'Invalidating CloudFront cache';
  }); // end DOM ready

})( jQuery );
