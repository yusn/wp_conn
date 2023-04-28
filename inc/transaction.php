<?php
/** 
 * 实现事务
 * https://core.trac.wordpress.org/ticket/9422
 */

// start transaction
function conn_start($transaction_id) {
  global $wp_transaction_id;
  if ( !isset($wp_transaction_id) ) {
    $wp_transaction_id = $transaction_id;
    $wpdb->query("START TRANSACTION;");
  }
}

// rollback transaction
function conn_rollback($wp_error) {
  global $wp_transaction_id;
  unset($wp_transaction_id);
  $wpdb->query("ROLLBACK;");
  wp_die($wp_error);
}

// commit transaction
function conn_commit($transaction_id) {
  global $wp_transaction_id;
  if ( isset($wp_transaction_id) && $wp_transaction_id == $transaction_id ) {
    unset($wp_transaction_id);
    $wpdb->query("COMMIT;");
  }
}


?>