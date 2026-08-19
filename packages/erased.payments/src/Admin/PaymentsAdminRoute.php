<?php
declare(strict_types=1);

namespace ErasedPayments\Admin;

final class PaymentsAdminRoute
{
    public function handle(): void
    {
        $providers = ['none' => 'Disabled', 'stripe' => 'Stripe', 'paypal' => 'PayPal', 'vipps' => 'Vipps MobilePay', 'klarna' => 'Klarna', 'bank' => 'Manual bank transfer'];
        $providerBlurbs = ['none' => 'No checkout provider active.', 'stripe' => 'Cards, wallets, global.', 'paypal' => 'PayPal balance, cards, credit.', 'vipps' => 'Nordic mobile payments.', 'klarna' => 'Buy now, pay later.', 'bank' => 'Manual transfer, no gateway.'];
        $currencies = ['NOK', 'EUR', 'USD', 'GBP', 'SEK', 'DKK', 'PLN'];
        $providerFields = [
            'stripe' => ['publishable_key', 'secret_key', 'webhook_secret'],
            'paypal' => ['client_id', 'client_secret'],
            'vipps' => ['merchant_serial_number', 'client_id', 'client_secret', 'subscription_key'],
            'klarna' => ['username', 'password'],
            'bank' => ['account_name', 'account_number', 'iban', 'bic', 'instructions'],
        ];

        if (($_GET['action'] ?? '') === 'export_transactions') {
            $format = strtolower((string)($_GET['format'] ?? 'csv'));
            $scope = (string)($_GET['scope'] ?? 'recent');
            if (!in_array($format, ['csv', 'txt'], true)) $format = 'csv';
            if (!in_array($scope, ['recent', 'all'], true)) $scope = 'recent';
            $sql = 'SELECT id,provider,amount_minor,currency,status,provider_reference,created_at,updated_at FROM payment_transactions ORDER BY created_at DESC' . ($scope === 'recent' ? ' LIMIT 50' : '');
            $rows = db()->query($sql)->fetchAll();
            $filename = 'erased-transactions-' . $scope . '-' . date('Y-m-d-His') . '.' . $format;
            header('Content-Type: ' . ($format === 'csv' ? 'text/csv' : 'text/plain') . '; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('X-Content-Type-Options: nosniff');
            $out = fopen('php://output', 'wb');
            if ($format === 'csv') {
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['ID', 'Provider', 'Amount', 'Currency', 'Status', 'Provider reference', 'Created', 'Updated']);
                foreach ($rows as $row) fputcsv($out, [(int)$row['id'], $row['provider'], number_format(((int)$row['amount_minor']) / 100, 2, '.', ''), $row['currency'], $row['status'], $row['provider_reference'] ?? '', $row['created_at'], $row['updated_at'] ?? '']);
            } else {
                fwrite($out, "ERASED CMS transaction export\nScope: " . $scope . "\nGenerated: " . date('c') . "\n\n");
                fwrite($out, "ID\tProvider\tAmount\tCurrency\tStatus\tProvider reference\tCreated\tUpdated\n");
                foreach ($rows as $row) {
                    $values = [(int)$row['id'], $row['provider'], number_format(((int)$row['amount_minor']) / 100, 2, '.', ''), $row['currency'], $row['status'], $row['provider_reference'] ?? '', $row['created_at'], $row['updated_at'] ?? ''];
                    fwrite($out, implode("\t", array_map(static fn($v) => str_replace(["\r", "\n", "\t"], ' ', (string)$v), $values)) . "\n");
                }
            }
            fclose($out);
            audit('payments.export', ['scope' => $scope, 'format' => $format, 'count' => count($rows)]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $action = (string)($_POST['action'] ?? 'save');
            $gateway = (string)($_POST['payment_gateway'] ?? 'none');
            if (!array_key_exists($gateway, $providers)) $gateway = 'none';
            $currency = (string)($_POST['payment_currency'] ?? 'NOK');
            if (!in_array($currency, $currencies, true)) $currency = 'NOK';
            $mode = (string)($_POST['payment_mode'] ?? 'test');
            if (!in_array($mode, ['test', 'live'], true)) $mode = 'test';

            set_setting('payment_gateway', $gateway);
            set_setting('payment_currency', $currency);
            set_setting('payment_test_mode', $mode === 'test' ? '1' : '0');
            set_setting('payment_statement_descriptor', trim((string)($_POST['payment_statement_descriptor'] ?? '')));
            set_setting('payment_success_url', trim((string)($_POST['payment_success_url'] ?? '/payment/success')));
            set_setting('payment_cancel_url', trim((string)($_POST['payment_cancel_url'] ?? '/payment/cancel')));
            set_setting('payment_webhook_url', trim((string)($_POST['payment_webhook_url'] ?? '/webhooks/payments')));
            foreach ($providers as $code => $label) {
                if ($code === 'none') continue;
                set_setting('payment_' . $code . '_enabled', $gateway === $code ? '1' : '0');
                foreach ($providerFields[$code] ?? [] as $field) {
                    $key = 'payment_' . $code . '_' . $field;
                    $value = (string)($_POST[$key] ?? '');
                    if ($value !== '' || !str_contains($field, 'secret') && !in_array($field, ['password', 'subscription_key'], true)) set_setting($key, trim($value));
                }
            }

            if ($action === 'test') {
                $required = match ($gateway) {
                    'stripe' => ['payment_stripe_publishable_key', 'payment_stripe_secret_key'],
                    'paypal' => ['payment_paypal_client_id', 'payment_paypal_client_secret'],
                    'vipps' => ['payment_vipps_merchant_serial_number', 'payment_vipps_client_id', 'payment_vipps_client_secret', 'payment_vipps_subscription_key'],
                    'klarna' => ['payment_klarna_username', 'payment_klarna_password'],
                    'bank' => ['payment_bank_account_name', 'payment_bank_account_number'],
                    default => [],
                };
                $missing = [];
                foreach ($required as $key) if (trim(setting($key, '')) === '') $missing[] = $key;
                if ($gateway === 'none') flash('error', 'Choose a payment provider before validating the fields.');
                elseif ($missing) flash('error', 'Configuration is incomplete. Fill in all required fields for ' . $providers[$gateway] . '.');
                else flash('success', $providers[$gateway] . ' has all required configuration fields. This validates saved fields only; no provider connection or payment was attempted.');
                audit('payments.configuration_validate', ['gateway' => $gateway, 'valid' => !$missing]);
                redirect('/admin/payments');
            }

            audit('payments.settings', ['gateway' => $gateway, 'currency' => $currency, 'mode' => $mode]);
            flash('success', 'Payment configuration saved.');
            redirect('/admin/payments');
        }

        $selected = setting('payment_gateway', '');
        if ($selected === '' || !array_key_exists($selected, $providers)) {
            $selected = 'none';
            foreach (['stripe', 'paypal', 'vipps', 'klarna', 'bank'] as $provider) if (setting('payment_' . $provider . '_enabled') === '1') { $selected = $provider; break; }
        }
        $mode = setting('payment_test_mode', '1') === '1' ? 'test' : 'live';
        $currency = setting('payment_currency', 'NOK');
        $tx = db()->query('SELECT * FROM payment_transactions ORDER BY created_at DESC LIMIT 50')->fetchAll();

        $option = function (string $value, string $label, string $selected): string { return '<option value="' . e($value) . '"' . ($selected === $value ? ' selected' : '') . '>' . e($label) . '</option>'; };
        $field = function (string $name, string $label, string $type = 'text', string $help = '', bool $required = false): string {
            $value = setting($name, '');
            $placeholder = ($type === 'password' && $value !== '') ? 'Saved — leave blank to keep current value' : '';
            return '<div class="fslot"><label>' . e($label) . ($required ? ' *' : '') . '<input type="' . e($type) . '" name="' . e($name) . '" value="' . ($type === 'password' ? '' : e($value)) . '" placeholder="' . e($placeholder) . '"></label>' . ($help !== '' ? '<p class="muted" style="margin-top:.35rem;font-size:.75rem;">' . e($help) . '</p>' : '') . '</div>';
        };
        $corners = '<div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>';

        $h = '<p class="muted">Choose one provider and configure its credentials, currency, environment, and return URLs.</p>';
        $h .= '<form method="post" id="payment-config-form"><input type="hidden" name="csrf" value="' . csrf() . '">';

        $providerLogos = [
            'none' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke-width="2.3" stroke-linecap="round"><line x1="12" y1="3" x2="12" y2="11"></line><path d="M6.5 6.5a8 8 0 1 0 11 0"></path></svg>',
            'stripe' => 'stripe',
            'paypal' => '<span class="pp-1">Pay</span><span class="pp-2">Pal</span>',
            'vipps' => 'Vipps',
            'klarna' => 'Klarna',
            'bank' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10 12 4l9 6"></path><line x1="4" y1="10" x2="20" y2="10"></line><line x1="5" y1="10" x2="5" y2="19"></line><line x1="10" y1="10" x2="10" y2="19"></line><line x1="14" y1="10" x2="14" y2="19"></line><line x1="19" y1="10" x2="19" y2="19"></line><line x1="3" y1="19" x2="21" y2="19"></line></svg>',
        ];
        $h .= '<div class="panel">' . $corners . '<div class="panel-head"><h2>Payment Provider</h2></div><div class="panel-body"><div class="provider-pick-grid">';
        foreach ($providers as $code => $label) {
            $checked = $selected === $code ? ' checked' : '';
            $h .= '<label class="provider-pick-card" data-provider-pick="' . e($code) . '"><input type="radio" class="provider-pick-input" name="payment_gateway" value="' . e($code) . '"' . $checked . '><span class="provider-logo provider-logo-' . e($code) . '">' . ($providerLogos[$code] ?? '') . '</span><span class="provider-pick-name">' . e($label) . '</span></label>';
        }
        $h .= '</div></div></div>';

        $h .= '<div class="panel"><div class="panel-body"><div class="fgrid three"><div class="fslot"><label>Currency<select name="payment_currency">';
        foreach ($currencies as $value) $h .= $option($value, $value, $currency);
        $h .= '</select></label></div><div class="fslot"><label>Environment<select name="payment_mode">' . $option('test', 'Test / sandbox', $mode) . $option('live', 'Live / production', $mode) . '</select></label><p class="muted" style="margin-top:.35rem;font-size:.75rem;">Use test mode until credentials and webhooks are verified.</p></div><div class="fslot"><label>Statement descriptor<input name="payment_statement_descriptor" maxlength="22" value="' . e(setting('payment_statement_descriptor', 'ERASED')) . '"></label><p class="muted" style="margin-top:.35rem;font-size:.75rem;">Text customers may see on a bank statement.</p></div></div></div></div>';

        $h .= '<div class="provider-panel" data-provider="stripe"><div class="panel">' . $corners . '<div class="panel-head"><h2>Stripe Configuration</h2></div><div class="panel-body"><div class="fgrid">' . $field('payment_stripe_publishable_key', 'Publishable key', 'text', 'Usually starts with pk_test_ or pk_live_.', true) . $field('payment_stripe_secret_key', 'Secret key', 'password', 'Stored in CMS settings; never displayed after saving.', true) . $field('payment_stripe_webhook_secret', 'Webhook signing secret', 'password', 'Usually starts with whsec_.') . '</div></div></div></div>';
        $h .= '<div class="provider-panel" data-provider="paypal"><div class="panel">' . $corners . '<div class="panel-head"><h2>PayPal Configuration</h2></div><div class="panel-body"><div class="fgrid">' . $field('payment_paypal_client_id', 'Client ID', 'text', 'From your PayPal developer application.', true) . $field('payment_paypal_client_secret', 'Client secret', 'password', 'Encrypted at rest and not shown again.', true) . '</div></div></div></div>';
        $h .= '<div class="provider-panel" data-provider="vipps"><div class="panel">' . $corners . '<div class="panel-head"><h2>Vipps MobilePay Configuration</h2></div><div class="panel-body"><div class="fgrid">' . $field('payment_vipps_merchant_serial_number', 'Merchant serial number', 'text', 'Your merchant identifier.', true) . $field('payment_vipps_client_id', 'Client ID', 'text', 'API client ID.', true) . $field('payment_vipps_client_secret', 'Client secret', 'password', 'API client secret.', true) . $field('payment_vipps_subscription_key', 'Subscription key', 'password', 'Primary subscription key.', true) . '</div></div></div></div>';
        $h .= '<div class="provider-panel" data-provider="klarna"><div class="panel">' . $corners . '<div class="panel-head"><h2>Klarna Configuration</h2></div><div class="panel-body"><div class="fgrid">' . $field('payment_klarna_username', 'API username', 'text', 'Klarna API credential username.', true) . $field('payment_klarna_password', 'API password', 'password', 'Klarna API credential password.', true) . '<div class="fslot"><label>Region<select name="payment_klarna_region">' . $option('eu', 'Europe', setting('payment_klarna_region', 'eu')) . $option('na', 'North America', setting('payment_klarna_region', 'eu')) . $option('oc', 'Oceania', setting('payment_klarna_region', 'eu')) . '</select></label></div></div></div></div></div>';
        $h .= '<div class="provider-panel" data-provider="bank"><div class="panel">' . $corners . '<div class="panel-head"><h2>Manual Bank Transfer</h2></div><div class="panel-body"><div class="fgrid">' . $field('payment_bank_account_name', 'Account holder', 'text', 'Name shown to the customer.', true) . $field('payment_bank_account_number', 'Account number', 'text', 'Local account number.', true) . $field('payment_bank_iban', 'IBAN') . $field('payment_bank_bic', 'BIC / SWIFT') . '</div><div class="fslot" style="margin-top:1rem;"><label>Payment instructions<textarea name="payment_bank_instructions">' . e(setting('payment_bank_instructions', 'Use the order number as the payment reference.')) . '</textarea></label></div></div></div></div>';
        $h .= '<div class="provider-panel" data-provider="none"><div class="panel">' . $corners . '<div class="panel-body"><p class="muted">Payments are disabled. Choose a provider above to display its setup fields.</p></div></div></div>';

        $h .= '<div class="panel">' . $corners . '<div class="panel-head"><h2>Payment Routing</h2></div><div class="panel-body"><p class="muted">This screen stores provider settings. Checkout, signed webhooks and provider API calls require an active commerce integration.</p><div class="fgrid three"><div class="fslot"><label>Success URL<input name="payment_success_url" value="' . e(setting('payment_success_url', '/payment/success')) . '"></label></div><div class="fslot"><label>Cancel URL<input name="payment_cancel_url" value="' . e(setting('payment_cancel_url', '/payment/cancel')) . '"></label></div><div class="fslot"><label>Webhook path<input name="payment_webhook_url" value="' . e(setting('payment_webhook_url', '/webhooks/payments')) . '"></label><p class="muted" style="margin-top:.35rem;font-size:.75rem;">Configure the complete public URL after installing a commerce integration.</p></div></div></div></div>';

        $h .= '<div class="form-actions"><button type="submit" class="btn" name="action" value="save">Save payment configuration</button><button type="submit" class="btn ghost" name="action" value="test">Validate required fields</button></div></form>';

        $h .= '<div class="panel">' . $corners . '<div class="panel-head"><h2>Recent Transactions</h2></div><div class="panel-body"><p class="muted">Transaction records created by active commerce integrations appear here.</p><div class="admin-row-actions" style="margin-bottom:1rem;"><a class="btn ghost" href="/admin/payments?action=export_transactions&amp;scope=recent&amp;format=csv">Recent CSV</a><a class="btn ghost" href="/admin/payments?action=export_transactions&amp;scope=recent&amp;format=txt">Recent TXT</a><a class="btn ghost" href="/admin/payments?action=export_transactions&amp;scope=all&amp;format=csv">All CSV</a><a class="btn ghost" href="/admin/payments?action=export_transactions&amp;scope=all&amp;format=txt">All TXT</a></div><div style="overflow-x:auto;"><table><thead><tr><th>Provider</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead><tbody>';
        foreach ($tx as $t) $h .= '<tr><td>' . e($t['provider']) . '</td><td>' . number_format($t['amount_minor'] / 100, 2) . ' ' . e($t['currency']) . '</td><td>' . e($t['status']) . '</td><td>' . e($t['created_at']) . '</td></tr>';
        $h .= ($tx ? '' : '<tr><td colspan="4" class="admin-row-empty">No payment transactions yet.</td></tr>') . '</tbody></table></div></div></div>';

        $h .= '<style>
.provider-pick-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(123px,1fr));gap:11px;}
.admin-area label.provider-pick-card{position:relative;display:flex;flex-direction:column;align-items:center;gap:9px;padding:14px 8px;margin-bottom:0;border:0;border-radius:7px;background:var(--sheet);cursor:pointer;text-align:center;text-transform:none;}
.provider-pick-input{position:absolute;opacity:0;width:1px;height:1px;pointer-events:none;}
.provider-logo{display:flex;align-items:center;justify-content:center;width:100%;height:34px;border-radius:6px;font-family:var(--font-body);font-size:14px;font-weight:700;}
.provider-pick-name{display:block;font-family:var(--font-mono);text-transform:uppercase;font-size:11px;font-weight:700;letter-spacing:.03em;color:var(--ink);}
.provider-logo-none,.provider-logo-bank{background:var(--accent);}
.provider-logo-none svg,.provider-logo-bank svg{stroke:var(--paper);}
.provider-logo-stripe{background:#f6f9fc;font-style:italic;font-weight:400;letter-spacing:-.02em;color:#635bff;}
.provider-logo-paypal{background:#fff;}
.provider-logo-paypal .pp-1,.provider-logo-paypal .pp-2{font-weight:800;font-style:italic;}
.provider-logo-paypal .pp-1{color:#00457c;}
.provider-logo-paypal .pp-2{color:#0079c1;}
.provider-logo-vipps{background:#ff5b24;font-weight:800;color:#fff;}
.provider-logo-klarna{background:#ffa8cd;font-weight:800;color:#0a0a0a;}
</style>';

        $h .= '<script>
(function(){
 var radios=document.querySelectorAll(\'input[name="payment_gateway"]\');
 function showPaymentProvider(){
  var selected="none";
  radios.forEach(function(r){if(r.checked)selected=r.value;});
  document.querySelectorAll(".provider-panel").forEach(function(panel){panel.hidden=panel.dataset.provider!==selected;});
 }
 radios.forEach(function(r){r.addEventListener("change",showPaymentProvider);});
 showPaymentProvider();
})();
</script>';

        $page = '<div class="title-row"><div><p class="kicker">SHEET ' . e(admin_sheet_code('/admin/payments')) . ' &middot; SYSTEM</p><h1>Payments</h1><p>Configure a payment provider, currency, and routing for checkout.</p></div></div>' . $h;
        layout('Payments', $page, true);
        exit;
    }
}
