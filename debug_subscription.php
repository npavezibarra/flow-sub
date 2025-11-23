<?php
require_once('/Users/nicolaspavez/Local Sites/devvillegas/app/public/wp-load.php');
require_once(dirname(__FILE__) . '/includes/class-flow-sub-api.php');

$api_key = get_option('flow_sub_api_key');
$secret_key = get_option('flow_sub_secret_key');
$api = new Flow_Sub_API($api_key, $secret_key);

// The ID from the user's screenshot
$subscription_id = 'sus_v1a6a63890';

echo "Fetching subscription: $subscription_id\n";
$sub_data = $api->get_subscription($subscription_id);

print_r($sub_data);
