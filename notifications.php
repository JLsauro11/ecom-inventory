<?php
require __DIR__.'/lib.php';require_login();
if($_SERVER['REQUEST_METHOD']==='GET'&&($_GET['mode']??'')==='bell'){header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');ob_start();system_notifications_bell();echo json_encode(['html'=>ob_get_clean()]);exit;}
try{if($_SERVER['REQUEST_METHOD']!=='POST')throw new Exception('Use the notification controls.');verify_csrf();system_notification_mark_read($_POST['audit_through']??0,$_POST['count_through']??0);flash('Updates marked as read. Pending tasks still require processing.');}catch(Throwable $e){flash(safe_error_message($e),'danger');}header('Location: index.php?page=notifications');exit;
