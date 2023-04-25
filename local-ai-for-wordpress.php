<?php
/*
Plugin Name: Local AI For Wordpress
Description: A plugin to edit posts in bulk using the Oobabooga API.
Version: 0.1 Alpha
Author: CheshireAI
*/

function bpe_add_nonce_field() {
wp_nonce_field('bpe_process_action', 'bpe_nonce');
}

function bpe_admin_menu() {
add_menu_page(
'Bulk Post Editor',
'Bulk Post Editor',
'manage_options',
'bulk-post-editor',
'bpe_admin_page',
'dashicons-edit'
);
}
add_action('admin_menu', 'bpe_admin_menu');

function bpe_admin_page() {
if (!function_exists('wp_create_nonce')) {
require_once ABSPATH . 'wp-includes/pluggable.php';
}

if (isset($_POST['bpe_nonce']) && wp_verify_nonce($_POST['bpe_nonce'], 'bpe_process_action')) {
bpe_process_posts();
}
?>
<div class="wrap">
<h1>Bulk Post Editor</h1>
<form method="post">
<?php bpe_add_nonce_field(); ?>

<table class="form-table">
<!-- Form fields go here -->
<tr>
<th scope="row"><label for="api_endpoint_url">API Endpoint URL</label></th>
<td><input name="api_endpoint_url" id="api_endpoint_url" type="text" class="regular-text" value="<?php echo esc_attr(get_option('bpe_api_endpoint_url', '')); ?>" required /></td>
</tr>
<tr>
<th scope="row"><label for="prompt">Prompt</label></th>
<td><input name="prompt" id="prompt" type="text" class="regular-text" required /></td>
</tr>
<tr>
<th scope="row">API Parameters</th>
<td>
<!-- Add fields for API parameters here -->
<!-- Example: -->
<label for="max_new_tokens">Max New Tokens</label>
<input name="max_new_tokens" id="max_new_tokens" type="number" class="regular-text" value="200" /><br />

<label for="do_sample">Do Sample</label>
<input name="do_sample" id="do_sample" type="checkbox" checked /><br />

<label for="temperature">Temperature</label>
<input name="temperature" id="temperature" type="number" class="regular-text" step="0.1" value="0.72" /><br />

<label for="top_p">Top P</label>
<input name="top_p" id="top_p" type="number" class="regular-text"step="0.1" value="0.73" /><br />

<label for="typical_p">Typical P</label>
<input name="typical_p" id="typical_p" type="number" class="regular-text" step="0.1" value="1" /><br/>

<label for="repetition_penalty">Repetition Penalty</label>
<input name="repetition_penalty" id="repetition_penalty" type="number" class="regular-text" step="0.1" value="1.1" /><br />

<label for="encoder_repetition_penalty">Encoder Repetition Penalty</label>
<input name="encoder_repetition_penalty" id="encoder_repetition_penalty" type="number" class="regular-text" step="0.1" value="1.0"/><br />

<label for="top_k">Top K</label>
<input name="top_k" id="top_k" type="number" class="regular-text"value="0" /><br />

<label for="min_length">Minimum Length</label>
<input name="min_length" id="min_length" type="number" class="regular-text" value="0" /><br />
<label for="no_repeat_ngram_size">No Repeat Ngram Size</label>
<input name="no_repeat_ngram_size" id="no_repeat_ngram_size" type="number" class="regular-text" value="0" /><br />
<label for="num_beams">Num Beams</label>
<input name="num_beams" id="num_beams" type="number" class="regular-text" value="1" /><br />

<label for="penalty_alpha">Penalty Alpha</label>
<input name="penalty_alpha" id="penalty_alpha" type="number" class="regular-text" value="0" /><br />

<label for="length_penalty">Length Penalty</label>
<input name="length_penalty" id="length_penalty" type="number" class="regular-text" value="1" /><br />

<label for="early_stopping">Early Stopping</label>
<input name="early_stopping" id="early_stopping" type="checkbox" /><br />

<label for="seed">Seed</label>
<input name="seed" id="seed" type="number" class="regular-text" value="-1" /><br />

<label for="add_bos_token">Add BOS Token</label>
<input name="add_bos_token" id="add_bos_token" type="checkbox" checked /><br />

<label for="truncation_length">Truncation Length</label>
<input name="truncation_length" id="truncation_length" type="number" class="regular-text" value="2048" /><br />

<label for="ban_eos_token">Ban EOS Token</label>
<input name="ban_eos_token" id="ban_eos_token" type="checkbox" /><br />

<label for="skip_special_tokens">Skip Special Tokens</label>
<input name="skip_special_tokens" id="skip_special_tokens" type="checkbox" checked /><br />

<label for="stopping_strings">Stopping Strings (comma-separated)</label>
<input name="stopping_strings" id="stopping_strings" type="text" class="regular-text" value="" /><br/>

</td>
</tr>
<tr>
<th scope="row">Edit Target</th>
<td>
<label><input type="radio" name="edit_target"value="title" checked> Edit Post Title</label><br>
<label><input type="radio" name="edit_target"value="content"> Edit Post Content</label>
</td>
</tr>
<tr>
<th scope="row">Select Posts</th>
<td>
<?php
$posts = get_posts(array('post_type' => 'post', 'numberposts' => -1));
foreach ($posts as $post) {
?>
<label><input type="checkbox" name="post_ids[]" value="<?php echo$post->ID; ?>"> <?php echo get_the_title($post->ID); ?></label><br>
<?php
}
?>
</td>
</tr>
</table>

<p class="submit">
<input type="submit" name="submit" id="submit" class="button button-primary" value="Process Posts" />
</p>
</form>
</div>
<?php
}

function bpe_call_personal_api($content, $endpoint, $api_parameters, $prompt) {
// Create the payload combining the prompt and other parameters
$payload = json_encode(array($prompt, $api_parameters));
$payload_data = array("data" => array($payload));

$args = array(
'body' => wp_json_encode($payload_data),
'headers' => array(
'Content-Type' => 'application/json',
),
'timeout' => 60,
'method' => 'POST',
);

$response = wp_remote_post($endpoint, $args);

if (is_wp_error($response)) {
$error_message = $response->get_error_message();
echo '<div class="notice notice-error is-dismissible"><p>API Error: ' . $error_message . '</p></div>';
return $content;
}

$body = wp_remote_retrieve_body($response);
$json_response = json_decode($body, true);

if (isset($json_response['data'][0])) {
$reply = $json_response['data'][0];
// Remove the original prompt from the API response
$processed_content = substr($reply, strlen($prompt));
return $processed_content;
} else {
echo '<div class="notice notice-error is-dismissible"><p>API Error: Invalid response received.</p></div>';
echo '<pre>Debug JSON: ' . print_r($json_response, true) . '</pre>'; // Debug JSON
echo '<pre>Debug Response: ' . print_r($response, true) .'</pre>'; // Debug full response
return $content;
}
}

function bpe_process_posts() {
// Define the API endpoint here.
// Get the API endpoint URL from the form and update the option
$endpoint = sanitize_text_field($_POST['api_endpoint_url']);
update_option('bpe_api_endpoint_url', $endpoint);

// (The rest of the function remains unchanged)

// Set API parameters from the submitted form
// Set API parameters from the submitted form
$api_parameters = array(
'max_new_tokens' => intval($_POST['max_new_tokens']),
'do_sample' => isset($_POST['do_sample']) ? true : false,
'temperature' => floatval($_POST['temperature']),
'top_p' => floatval($_POST['top_p']),
'typical_p' => floatval($_POST['typical_p']),
'repetition_penalty' => floatval($_POST['repetition_penalty']),
'encoder_repetition_penalty' => floatval($_POST['encoder_repetition_penalty']),
'top_k' => intval($_POST['top_k']),
'min_length' => intval($_POST['min_length']),
'no_repeat_ngram_size' => intval($_POST['no_repeat_ngram_size']),
'num_beams' => intval($_POST['num_beams']),
'penalty_alpha' => floatval($_POST['penalty_alpha']),
'length_penalty' => floatval($_POST['length_penalty']),
'early_stopping' => isset($_POST['early_stopping']) ? true : false,
'seed' => intval($_POST['seed']),
'add_bos_token' => isset($_POST['add_bos_token']) ? true : false,
'truncation_length' => intval($_POST['truncation_length']),
'ban_eos_token' => isset($_POST['ban_eos_token']) ? true : false,
'skip_special_tokens' => isset($_POST['skip_special_tokens']) ? true : false,
'stopping_strings' => sanitize_text_field($_POST['stopping_strings']),
);

// (same as the previous code you provided)

// Get the prompt
$prompt = sanitize_text_field($_POST['prompt']);

// Get the edit target
$edit_target = sanitize_text_field($_POST['edit_target']);

// Get selected post IDs
$selected_post_ids = isset($_POST['post_ids']) ? $_POST['post_ids'] : array();

if (!empty($selected_post_ids)) {
foreach ($selected_post_ids as $post_id) {
// Get the post
$post = get_post($post_id);

if ($edit_target === 'title') {
$content = $post->post_title;
} else {
$content = $post->post_content;
}

// Send the content to the API for processing
$processed_content = bpe_call_personal_api($content, $endpoint, $api_parameters, $prompt);

if ($edit_target === 'title') {
$post->post_title = $processed_content;
} else {
$post->post_content = $processed_content;
}

// Update the post
wp_update_post($post);
}
}
}
