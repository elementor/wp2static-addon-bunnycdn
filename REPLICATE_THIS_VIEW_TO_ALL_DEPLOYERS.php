
<h2>Deployment Batch Size</h2>

<select name="deployBatchSize" id="deployBatchSize">

    <?php
    // TODO: shift this into helper function for select
    $increments = array( 1, 5, 10, 25, 50, 100, 500, 1000, 999999 );

    foreach ( $increments as $increment ) :
        if ( $increment == 999999 ) : ?>
            <option value="999999"<?php echo $this->options->deployBatchSize == $increment ? ' selected' : ''; ?>>Maximum</option>
    <?php else : ?>
              <option value="<?php echo $increment; ?>"<?php echo $this->options->deployBatchSize == $increment ? ' selected' : ''; ?>><?php echo $increment; ?></option>

    <?php endif;
          endforeach; ?>
</select>

<p>This is set to 1, by default, in order to avoid execution limit timeouts on restricted environments, such as shared hosting servers. Each increment is the amount of files the server will try to deploy on each request. Incrementing this will speed up your exports, by processing more are a time. If your export is failing, due to execution limits or API rate limits being reached, try setting this to a lower number.</p>

<h2>API Request Delay</h2>

<select name="delayBetweenAPICalls" id="delayBetweenAPICalls">

<?php
  // TODO: shift this into helper function for select
  $increments = array( 0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1, 2, 3, 4, 10 );

foreach ( $increments as $increment ) : ?>
            <option value="<?php echo $increment; ?>"<?php echo $this->options->delayBetweenAPICalls == $increment ? ' selected' : ''; ?>><?php echo $increment; ?></option>
    <?php endforeach; ?>

</select>

<p>This is set to 0, by default, but if your deploy is hitting the remote API too rapidly for their rate limit, you can increase this to add a delay between each API request.</p>

