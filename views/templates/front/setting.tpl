<div class="panel">
    <div class="alert alert-info">
        <img src="{$url_logo}" style="float:left; margin-right:15px;" alt="PlacetoPay" height="48">
        <p>
            <strong>
                {l s='This module allows you to accept payments by' mod='placetopaypayment'} {$client}.
            </strong>
        </p>
        <p>
            Vr: {$version} {$warning_compliancy}
        </p>
    </div>

    {if !$is_set_credentials}
        <div class="alert alert-warning">
            <p>
                {$isset_credentials}
            </p>
        </div>
    {/if}

    <div class="panel-body">
        <form class="form-horizontal">
            <div class="form-group">
                <label class="control-label col-lg-3">
                    {l s='Notification URL' mod='placetopaypayment'}
                </label>
                <div class="col-lg-9">
                    <span style="font-size: 16px;">{$url_notification}</span>
                    <p class="help-block">
                        {$notify_translation}
                    </p>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-3">
                    {l s='Scheduler task path' mod='placetopaypayment'}
                </label>
                <div class="col-lg-9">
                    <span style="font-size: 16px;">{$schedule_task}</span>
                    <p class="help-block">
                        {l s='Set this task to validate payments with pending status in your site.' mod='placetopaypayment'}
                    </p>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-3">
                    {l s='Logs file path' mod='placetopaypayment'}
                </label>
                <div class="col-lg-9">
                    <span style="font-size: 16px;">{$log_file}</span>
                    <p class="help-block">
                        {l s='Debug messages are registered here (with debug mode enabled) and warnings and errors messages' mod='placetopaypayment'}
                        <a href="{$log_database}">{l s='here.' mod='placetopaypayment'}</a>
                    </p>
                </div>
            </div>
        </form>
    </div>
</div>
