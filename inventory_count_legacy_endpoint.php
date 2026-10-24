<?php
require_once __DIR__.'/lib.php';require_login();if(adjustments_ready()&&(int)($_POST['batch_id']??$_GET['id']??0)>0&&adjustment_is_new((int)($_POST['batch_id']??$_GET['id']))){header('Location: index.php?page=inventory_count&id='.(int)($_POST['batch_id']??$_GET['id']));exit;}
if(!inventory_update_ready()){header('Location: upgrade_v5.php');exit;}
$id=(int)($_POST['batch_id']??$_GET['id']??0);$action=$_POST['action']??'';$lock=null;$authorizedPost=false;
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();$lock=inventory_write_lock();
  if($action==='create'){ $id=count_create_snapshot($_POST);inventory_write_unlock($lock);$lock=null;header('Location: inventory_count.php?id='.$id.'&download=1');exit;}
  if(!can_action('upload_inventory_count'))throw new Exception('Your account cannot review inventory counts.');$authorizedPost=true;
  if($action==='upload'){
   $file=$_FILES['file']??[];if(empty($file['tmp_name'])||strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='xlsx')throw new Exception('Upload the completed XLSX count sheet.');
   $b=count_batch($id);try{$rows=XlsxReader::rows($file['tmp_name']);$errors=count_upload_rows($id,$rows,$file['name']);flash($errors?'The count has errors. Review the persistent error list.':'Actual counts uploaded. Review variance before applying.',$errors?'danger':'success');}catch(Throwable $readError){db()->prepare("UPDATE inventory_count_batches SET status='Error',filename=?,error_json=? WHERE id=? AND status<>'Applied'")->execute([basename($file['name']),json_encode([$readError->getMessage()]),$id]);throw $readError;}
  }elseif($action==='cancel'){count_cancel_pending($id);flash('Inventory count cancelled. No stock changed.');
  }elseif($action==='review_only'){
   $b=count_batch($id);if(!in_array($b['status'],['Pending Review','Review Only'],true))throw new Exception('Upload a valid sheet first.');db()->prepare("UPDATE inventory_count_batches SET status='Review Only' WHERE id=?")->execute([$id]);audit('Save inventory count review','Inventory Count',$b['batch_ref'],'No stock changed.');flash('Review saved. Inventory was not changed.');
  }elseif($action==='apply'){
   if(($_POST['review_ack']??'')!=='1')throw new Exception('Confirm that you reviewed the counts.');count_apply($id,trim($_POST['reason']??''),$_POST['allocation']??[]);flash('Inventory count adjustments applied.');
  }else throw new Exception('Invalid inventory count action.');
  inventory_write_unlock($lock);$lock=null;header('Location: index.php?page=inventory_count&id='.$id);exit;
 }
 $b=count_batch($id);
 if(isset($_GET['download'])){
  $path=tempnam(sys_get_temp_dir(),'rs8-count-');try{count_export_xlsx($id,$path);header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$b['batch_ref'].'.xlsx"');readfile($path);}finally{if(is_file($path))unlink($path);}exit;
 }
 if(isset($_GET['print'])){
  $rows=count_lines($id);?><!doctype html><html><head><meta charset="utf-8"><title>Inventory Count Sheet</title><style>body{font-family:Arial,sans-serif;color:#142235;margin:25px}table{width:100%;border-collapse:collapse;font-size:11px}th,td{border:1px solid #dce3eb;padding:9px}th{background:#eef2f6;text-align:left}td:nth-child(2){width:40%}h1{font-size:22px}.meta{font-size:12px;margin-bottom:15px}thead{display:table-header-group}tr{break-inside:avoid}@page{size:A4 landscape;margin:12mm}@media print{button{display:none}}</style><link rel="stylesheet" href="assets/typography.css?v=5.4.6-b"></head><body><button onclick="window.print()">Print / Save as PDF</button><h1>Physical Inventory Count Sheet</h1><div class="meta"><?=e($b['batch_ref'])?> · Snapshot <?=e($b['created_at'])?><br>On Hand includes sellable and non-sellable stock. Enter Good and Damaged separately. Leave uncounted items blank.</div><table><thead><tr><th>Master SKU</th><th>Product Description</th><th>On Hand</th><th>Actual Good</th><th>Actual Damaged</th><th>Variance</th><th>Remarks</th></tr></thead><tbody><?php foreach($rows as $line):?><tr><td><?=e($line['master_sku'])?></td><td><?=e($line['product_name'])?></td><td><?=(int)$line['on_hand']?></td><td></td><td></td><td></td><td></td></tr><?php endforeach;?></tbody></table><p>Counted by: ____________________ Date: ____________________</p></body></html><?php exit;
 }
 header('Location: index.php?page=inventory_count&id='.$id);exit;
}catch(Throwable $error){if(db()->inTransaction())db()->rollBack();if($authorizedPost&&$action==='apply'&&current_user()['role']==='Admin'){db()->prepare("UPDATE inventory_count_batches SET error_json=? WHERE id=? AND status<>'Applied'")->execute([json_encode([$error->getMessage()]),$id]);}inventory_write_unlock($lock);flash($error->getMessage(),'danger');header('Location: index.php?page=inventory_count'.($id?'&id='.$id:''));exit;}
