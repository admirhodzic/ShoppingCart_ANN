<?php if(isset($_REQUEST['dd'])){
	header("Content-Type: image/png");
	$d=$_REQUEST['dd'];
	$w=(int)(sqrt(strlen($d))+1);
	$im = @imagecreate($w, $w)
		or die("Cannot Initialize new GD image stream");
	$c1 = imagecolorallocate($im, 0,0,0);
	$c2 = imagecolorallocate($im, 255,255,255);
	imagefilledrectangle($im, 0,0, $w,$w, $c2);
	for($Y=0;$Y<$w;$Y++) for($X=0;$X<$w;$X++) if(@substr($d,$Y*$w+$X,1)=='1') imagesetpixel($im,$X,$Y,$c1);
	imagepng($im);
	imagedestroy($im);
	die();
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
	</head>
	<body>
<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$cnt=0;
$db = new mysqli("localhost", "fann", "fann", "fann");

function db($cmd){
	global $db;
	$a=array();
	$q1=$db->query($cmd);
	if($q1) while($r1=$q1->fetch_assoc()) $a[]=$r1; else return null;
	return $a;
}
$sum=0;
?>
<table class="table table-bordered"><thead><tr><th>Customer</th><th>Items#</th><th>Carts#</th><th>Data</th><th>Test cart</th><th>New cart</th><th>Result</th></tr></thead><tbody>
<?php
srand(time());
foreach(db('select id_customer from cart group by id_customer') as $r1) {
	$items=array();
	foreach(db('select item from cart where id_customer="'.$r1['id_customer'].'" group by item order by item') as $v) $items[]=$v['item'];
	if(count($items)>150) continue;
	$carts=array();
	foreach(db('select substring(tm,1,7) as id_cart,item from cart where id_customer="'.$r1['id_customer'].'" group by substring(tm,1,7),item order by substring(tm,1,7),item') as $r3){
	//foreach(db('select id_cart,item from cart where id_customer="'.$r1['id_customer'].'" group by id_cart,item order by id_cart,item') as $r3){
		if(!isset($carts[$r3['id_cart']])) $carts[$r3['id_cart']]=array();
		$carts[$r3['id_cart']][]=$r3['item'];
	}
	if(count($carts)<12) continue;
	
	
	echo '<tr><td>'.$r1['id_customer'].'</td><td>'.count($items).'</td><td>'.count($carts).'</td>';

	$train=array();
	foreach($carts as $id_cart=>$cart){
	
		$d=array_fill(0,count($items),0);
		foreach($cart as $i) $d[array_search($i,$items)]=1;
		$train[$id_cart]=$d;
	}
	echo '<td>';foreach($train as $k=>$t) if($k!=$id_cart){echo ' <img  src="?dd='.urlencode(implode('',$t)).'"/> | ';}echo '</td>';
	echo '<td>';foreach($train as $k=>$t) if($k==$id_cart){echo ' <img  src="?dd='.urlencode(implode('',$t)).'"/> ';}echo '</td>';

	$test_cart=$train[$id_cart];//this is not added to train data
	$tcarts=array();$n=0;foreach($train as $k=>$v) if($k!=$id_cart) {$tcarts[($n++)*2]=array_map(function($x){return $x==0?-1:1;},$v);}
	foreach($tcarts as $k=>$v) if(isset($tcarts[$k+2])) $tcarts[$k+1]=$tcarts[$k+2];
	set_time_limit(300) ;
	if(count($items)<400 && count($tcarts)>0){
		$o=train(count($items),count($items),$tcarts,$tcarts[count($tcarts)-1]);
	}
	else $o=array(-1);
	echo '<td><img src="?dd='.urlencode(implode('',array_map(function($x){ return $x<0?0:1;},$o))).'"/> </td><td>'.((count($o)==count($test_cart))?($s1=(round(array_sum(array_map(function($a,$b){return ($a==1 && $b>=0) ?1:0;},$test_cart,$o))*100/(max(array_sum(array_map(function($x){return $x>=0?1:0;},$o)),array_sum($test_cart))),0))):'-').'</td></tr>';
	$sum+=$s1;
	if($cnt++==15) break;
}
?></tbody></table><?php

print $sum/$cnt;
die();

function train($num_input,$num_output,$train_data,$run_data){
	$num_layers = 3;
	$num_neurons_hidden = $num_input*1;//better 1 than 1.5 or 2
	$desired_error = 0.01;
	$max_epochs = 500000;
	$epochs_between_reports = 1000;

	$ann = fann_create_standard($num_layers, $num_input, $num_neurons_hidden, $num_output);

	if ($ann) {
		fann_set_activation_function_hidden($ann, FANN_SIGMOID_SYMMETRIC_STEPWISE); //seems better than FANN_SIGMOID_SYMMETRIC
		fann_set_activation_function_output($ann, FANN_SIGMOID_SYMMETRIC_STEPWISE);

		$calc_out=null;$n=0;
		$tr=fann_create_train_from_callback(count($train_data)/2,$num_input,$num_output,function($nn,$in,$out) use (&$train_data, &$n){ $n++;return array('input'=>$train_data[($n-1)*2],'output'=>$train_data[($n-1)*2+1]);});
		if (fann_train_on_data($ann, $tr, $max_epochs, $epochs_between_reports, $desired_error)){
			$calc_out = fann_run($ann, $run_data);
		}
		fann_destroy_train($tr);
		fann_destroy($ann);
		return $calc_out;
	} else print "!$ann";
}
mysqli_close($db);
?>
