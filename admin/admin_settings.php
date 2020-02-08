<?php if ($this->status === 'failed') {
    echo "<div><p class='stgcdn_notice error'><strong style='text-transform:capitalize;color:#ce2020;margin-right:3px;'>$this->status :</strong> $this->error</p></div>";
} elseif ($this->status === 'success') {
    echo "<div><p class='stgcdn_notice success' style=''><strong style='text-transform:capitalize;color:#55af5a;margin-right:3px;'>$this->status :</strong> Your settings have been saved.</p></div>";
} ?>

<div class="stgcdn_admin">
    <div class="panel">
        <h1 class="title">Staging CDN</h1>
        <form action="?page=stgcdn-admin" method="post">
            <div style="display: flex; flex-direction: column; align-items: flex-start;">
                <label>Current URL media is being referenced from.</label>
                <input name="current_url" type="text" value="<?php echo isset($_POST['new_url']) && !empty($_POST['new_url']) ? $_POST['new_url'] : $current_url; ?>" style="min-width:350px" disabled/><br/>
                <label>Your Staging URL</label>
                <input name="staging_url" type="text" value="<?php echo $staging_url; ?>" style="min-width:350px" disabled/><br/>
                <label>New URL you would like to use</label>
                <input name="new_url" type="text" value="" style="min-width:350px"/>
                <input name="save_url" type="text" value="true" style="min-width:350px" hidden/>
            </div>
            <button type="submit" style="all: unset;padding: 15px 65px;width: 100%;max-width: 350px;box-sizing: border-box;margin-top: 15px;text-align: center;box-shadow: 0 0 1px black inset;">Update URL</button>
        </form>
    </div>

    <div class="panel" style="">
        <h1 class="title">Settings</h1>
        <form action="?page=stgcdn-admin" method="post">
            <div class="options_wrapper">
                <label for="local_checkbox" >
                <input id="local_checkbox" name="check_local" type="checkbox" value="enabled" <?php echo $local_check_setting === true ? 'checked' : ''; ?>/>
                Use sites own media if available?
                </label>
                <input name="save_settings" type="text" value="true" hidden/>
            </div>
            <button type="submit" style="">Update Settings</button>
        </form>
    </div>
        
</div>