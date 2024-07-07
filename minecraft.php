<?php
require_once __DIR__ . '/vendor/autoload.php';
use App\Services\Database\DatabaseService as DB;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
DB::instance();

$url = 'http://vanillaboxmc.ru/tiles/minecraft_overworld/markers.json';

$data = file_get_contents($url);
$data = json_decode($data)[3];
echo '<pre>';
//print_r($data->markers);
echo '</pre>';

$names = [];
foreach($data->markers as $region){
    $x = isset($region->points[0]->x) ? intval($region->points[0]->x) : 0;
    $x2 = isset($region->points[1]->x) ? intval($region->points[1]->x) : 0;
    $z = isset($region->points[0]->z) ? intval($region->points[0]->z) : 0;
    $z2 = isset($region->points[1]->z) ? intval($region->points[1]->z) : 0;

    $dif = abs(abs($x2)-abs($x));
    if ($dif>128){
        $reg =  '['.$x.','.$z.'] - ['.$x2.','.$z2.']';
        $v1 = mb_stripos($region->popup, ';">');
        $v2 = mb_stripos($region->popup, '</s');
        $v1 = $v1+3;
        $v2 = $v2-3;
        $reg_name = mb_substr($region->popup, $v1, abs($v2-$v1)+2);
        $reg_name = $reg_name === '</spa' ? 'Без имени': $reg_name;
        $db_reg = DB::findOne('minecraft', 'name=? AND x=? AND z=?', array($reg_name, $x, $z));
        $names[] = $reg_name.$x.$z;
        //если региона ранее не было в бд, значит он новый, добавляем в бд
        if (!$db_reg){;
            $add = DB::xdispense('minecraft');
            $add->name = $reg_name;
            $add->location = $reg;
            $add->x = $x;
            $add->z = $z;
            $add->active = 1;
            $add->updated_at = date('Y-m-d H:i:s');
            DB::store($add);
        }
    }
}


$regions = DB::getAll('SELECT * FROM minecraft');
$i=0;

echo '<div style="display: grid;grid-template-columns: 1fr 1fr 1fr 1fr;">';
foreach($regions as $r){
    $i++;
    //если среди текущих регионов нет региона из бд, значит он больше не активен, помечаем в бд
    if (!in_array($r['name'].$r['x'].$r['z'],$names)){
        $current = DB::findOne('minecraft', 'name=? AND x=? AND z=?', array($r['name'], $r['x'], $r['z']));
        if ($current){
            $edit = DB::load('minecraft', $current->id);
            $edit->active = 0;
            DB::store($edit);
        }

    }
    $style = ($r['active']==='1') ? '' : 'font-weight:bold; color:red;';
    if ($r['name']){
        echo '<span style="'.$style.'">'.$r['name'].' '.$r['location'].'</span>';
    }

}
echo '</div>';
echo 'Всего регионов: '.$i.'<br>';