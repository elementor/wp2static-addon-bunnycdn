<h2>BunnyCDN Deployment Options</h2>

<h3>BunnyCDN</h3>

<form
    name="wp2static-bunnycdn-save-options"
    method="POST"
    action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">

    <?php wp_nonce_field( $view['nonce_action'] ); ?>
    <input name="action" type="hidden" value="wp2static_bunnycdn_save_options" />

<table class="widefat striped">
    <tbody>
        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['bunnycdnAccountAPIKey']->name; ?>"
                ><?php echo $view['options']['bunnycdnAccountAPIKey']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['bunnycdnAccountAPIKey']->name; ?>"
                    name="<?php echo $view['options']['bunnycdnAccountAPIKey']->name; ?>"
                    type="password"
                    value="<?php echo $view['options']['bunnycdnAccountAPIKey']->value !== '' ?
                        \WP2Static\CoreOptions::encrypt_decrypt('decrypt', $view['options']['bunnycdnAccountAPIKey']->value) :
                        ''; ?>"
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['bunnycdnStorageZoneName']->name; ?>"
                ><?php echo $view['options']['bunnycdnStorageZoneName']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['bunnycdnStorageZoneName']->name; ?>"
                    name="<?php echo $view['options']['bunnycdnStorageZoneName']->name; ?>"
                    type="text"
                    value="<?php echo $view['options']['bunnycdnStorageZoneName']->value !== '' ? $view['options']['bunnycdnStorageZoneName']->value : ''; ?>"
                />
            </td>
        </tr>
    </tbody>
</table>

<br>

    <button class="button btn-primary">Save BunnyCDN Options</button>
</form>

