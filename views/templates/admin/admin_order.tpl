{if version_compare(_PS_VERSION_, '1.7.7.0', '>=')}
    <div class="card mt-4" id="placetopaypayment_details">
        <div class="card-header">
            <h3 class="card-header-title">
                <img src="{$icon}" class="material-icons" style="width: 10%" />
                {$title}
            </h3>
        </div>
        <div class="card-body">
            {foreach from=$details item=detail}
                <div class="input-group">
                    <div class="col-md-4 text-right">
                        <strong>{$detail.key}:</strong>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1">{$detail.value}</p>
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
{else}
    <div class="panel" id="placetopaypayment_details">
        <div class="panel-heading">
            <img src="{$icon}" style="width: 10%" />
            {$title}
        </div>
        <dl class="dl-horizontal">
            {foreach from=$details item=detail}
                <dt>{$detail.key|escape:'htmlall':'UTF-8'}:</dt>
                <dd>{$detail.value|escape:'htmlall':'UTF-8'}</dd>
            {/foreach}
        </dl>
    </div>
{/if}
