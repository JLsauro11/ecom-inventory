<?php
function registration_ready(){
 static $ready=null;if($ready!==null)return $ready;global $CFG;
 $q=db()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='users' AND COLUMN_NAME IN ('approval_status','approved_by','approved_at')");$q->execute([$CFG['db_name']]);return $ready=(int)$q->fetchColumn()===3;
}
function register_user_request($name,$username,$password,$confirm){
 if(!registration_ready())throw new Exception('Sign Up is not available yet. Ask the administrator to complete the system upgrade.');
 $name=trim($name);$username=trim($username);
 if($name===''||mb_strlen($name)>120)throw new Exception('Full name is required and must be at most 120 characters.');
 if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,79}$/D',$username))throw new Exception('Username must be 3-80 characters. Use letters, numbers, dots, underscores or hyphens.');
 if(strlen($password)<8||strlen($password)>72)throw new Exception('Password must be 8-72 characters.');
 if($password!==$confirm)throw new Exception('Passwords do not match.');
 $q=db()->prepare('INSERT INTO users(name,username,password_hash,role,active,must_change_password,approval_status) VALUES(?,?,?,\'Viewer / Management\',0,0,\'Pending\')');
 try{$q->execute([$name,$username,password_hash($password,PASSWORD_DEFAULT)]);}catch(PDOException $e){if($e->getCode()==='23000')throw new Exception('This username is already registered. Choose another username or contact the administrator.');throw $e;}
 return (int)db()->lastInsertId();
}
function review_registration($id,$decision,$role,$actor){
 if(current_user()['role']!=='Admin')throw new Exception('Only an administrator can review registrations.');
 if(!in_array($decision,['Approved','Rejected'],true))throw new Exception('Invalid registration decision.');
 if($decision==='Approved')validate_user_role($role);
 $pdo=db();$pdo->beginTransaction();try{
  $q=$pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$q->execute([$id]);$user=$q->fetch();
  if(!$user||!in_array($user['approval_status'],['Pending','Rejected'],true))throw new Exception('This account has already been reviewed or is not a registration request.');
  if($decision==='Rejected'&&$user['approval_status']!=='Pending')throw new Exception('This registration is already rejected.');
  if($decision==='Approved')$pdo->prepare("UPDATE users SET approval_status='Approved',active=1,role=?,approved_by=?,approved_at=NOW() WHERE id=?")->execute([$role,$actor,$id]);
  else $pdo->prepare("UPDATE users SET approval_status='Rejected',active=0,approved_by=?,approved_at=NOW() WHERE id=?")->execute([$actor,$id]);
  audit($decision==='Approved'?'Approve registration':'Reject registration','User',(string)$id,$user['username'].($decision==='Approved'?'; role: '.$role:''));$pdo->commit();
 }catch(Throwable $e){$pdo->rollBack();throw $e;}
}
