<?php if ($this->status === 'failed') {
    echo "<div><p class='stgcdn_notice error'><strong>" . __($this->status, $this::$textDomain) . ":</strong> " . __($this->error, $this::$textDomain) . "</p></div>";
} elseif ($this->status === 'success') {
    echo "<div><p class='stgcdn_notice success'><strong>" . __( sprintf( '%s',  $this->status ), $this::$textDomain ) . ":</strong> " . __('Your settings have been saved.', $this::$textDomain) . "</p></div>";
} ?>

<div class="stgcdn_admin">
    <div class="panel">
        <h1 class="title">Staging CDN</h1>
        <form action="?page=stgcdn-admin" method="post">
            <div class="options_wrapper">
                <label><?php _e('Current URL media is being referenced from.', $this::$textDomain); ?></label>
                <input name="stgcdn_current_url" type="text" value="<?php echo $_POST['stgcdn_updated_url'] ?? $replacement_url; ?>" disabled/><br/>
                <label><?php _e('Your Staging URL', $this::$textDomain); ?></label>
                <input name="stgcdn_staging_url" type="text" value="<?php echo $staging_url; ?>" disabled/><br/>
                <label><?php _e('New Replacement URL', $this::$textDomain); ?></label>
                <input name="stgcdn_new_url" type="text" value="" />
                <input name="stgcdn_save_url" type="text" value="true" hidden/>
            </div>
            <button type="submit">Update URL</button>
        </form>
    </div>

    <div class="panel">
        <h1 class="title"><?php _e('Settings', 'stgcdn'); ?></h1>
        <form action="?page=stgcdn-admin" method="post">
            <div class="options_wrapper">
                <label for="local_checkbox" >
                <input id="local_checkbox" name="stgcdn_check_local" type="checkbox" value="enabled" <?php echo $local_check_setting === true ? 'checked' : ''; ?>/>
                <?php _e('Use sites own media if available?', $this::$textDomain); ?>
                </label>
                <input name="stgcdn_save_settings" type="text" value="true" hidden/>
            </div>
            <button type="submit"><?php _e('Update Settings', $this::$textDomain); ?></button>
        </form>
    </div>
        
</div>