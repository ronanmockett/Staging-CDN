<?php
if ($this->status === 'failed') {
    echo "<div><p class='stgcdn_notice error'><strong>" . sprintf( __('%s', 'stgcdn'), $this->status ) . ":</strong> " . sprintf( __('%s', 'stgcdn'), $this->error ) . "</p></div>";
} elseif ($this->status === 'success') {
    echo "<div><p class='stgcdn_notice success'><strong>" . sprintf( __('%s', 'stgcdn'), $this->status ) . ":</strong> " . __('Your settings have been saved.', 'stgcdn') . "</p></div>";
} ?>

<div class="stgcdn_admin">
    <div class="panel">
        <h1 class="title">Staging CDN</h1>
        <form action="?page=stgcdn-admin" method="post">
            <div class="options_wrapper">
                <label><?php _e('Current URL media is being referenced from.', 'stgcdn'); ?></label>
                <span><?php echo isset($_POST['stgcdn_updated_url']) && !empty($_POST['stgcdn_updated_url']) ? esc_url($_POST['stgcdn_updated_url']) : $replacement_url; ?></span><br/>
                <label><?php _e('New Replacement URL', 'stgcdn'); ?></label>
                <input name="stgcdn_new_url" type="text" value="" />
                <input name="stgcdn_save_url" type="text" value="true" hidden/>
            </div>
			<div class="form_actions">
				<button type="submit"><?php _e('Update URL', 'stgcdn'); ?></button>
				<a class="" href="<?php echo add_query_arg('reset', '1'); ?>"><?php _e('Reset', 'stgcdn'); ?></a>
			</div>
        </form>
    </div>

    <div class="panel">
        <h1 class="title"><?php _e('Settings', 'stgcdn'); ?></h1>
        <form action="?page=stgcdn-admin" method="post">
            <div class="options_wrapper">
                <label for="local_checkbox" >
                <input id="local_checkbox" name="stgcdn_check_local" type="checkbox" <?php echo $this->check_local ? 'checked' : ''; ?>/>
                <?php _e('Use sites own media if available?', 'stgcdn'); ?>
                </label>
                <input name="stgcdn_save_settings" type="text" value="true" hidden/>
            </div>
            <button type="submit"><?php _e('Update Settings', 'stgcdn'); ?></button>
        </form>
    </div>
        
</div>